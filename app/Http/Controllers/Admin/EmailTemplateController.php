<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\EmailNotifications;
use App\Services\EmailTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplateController extends Controller
{
    public function index(): Response
    {
        $templates = [];

        foreach (EmailNotifications::EVENTS as $key => $event) {
            $template = EmailTemplates::get($key);
            $templates[] = [
                'key' => $key,
                'slug' => self::slug($key),
                'label' => $event['label'],
                'recipient' => $event['recipient'],
                'subject' => $template['subject'],
                'customized' => $template['customized'],
                'enabled' => EmailNotifications::eventEnabled($key),
            ];
        }

        return Inertia::render('admin/email/Templates', [
            'templates' => $templates,
            'emailEnabled' => EmailNotifications::enabled(),
        ]);
    }

    public function edit(string $slug): Response
    {
        $key = $this->eventKey($slug);
        $event = EmailNotifications::EVENTS[$key];
        $placeholders = [];

        foreach (EmailTemplates::placeholders($key) as $name => $placeholder) {
            $placeholders[] = ['key' => $name] + $placeholder;
        }

        return Inertia::render('admin/email/TemplateEdit', [
            'event' => [
                'key' => $key,
                'slug' => $slug,
                'label' => $event['label'],
                'recipient' => $event['recipient'],
                'description' => $event['description'],
                'button' => EmailTemplates::TEMPLATES[$key]['button'],
                'enabled' => EmailNotifications::eventEnabled($key),
            ],
            'template' => EmailTemplates::get($key),
            'defaults' => [
                'subject' => EmailTemplates::TEMPLATES[$key]['subject'],
                'body' => EmailTemplates::TEMPLATES[$key]['body'],
            ],
            'placeholders' => $placeholders,
            'limits' => ['subject' => EmailTemplates::MAX_SUBJECT, 'body' => EmailTemplates::MAX_BODY],
        ]);
    }

    public function update(Request $request, string $slug): RedirectResponse
    {
        $key = $this->eventKey($slug);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:'.EmailTemplates::MAX_SUBJECT],
            'body' => ['required', 'string', 'max:'.EmailTemplates::MAX_BODY],
        ]);

        $errors = [];
        foreach (['subject', 'body'] as $field) {
            $unknown = EmailTemplates::unknownPlaceholders($key, $validated[$field]);
            if ($unknown !== []) {
                $errors[$field] = 'Unknown placeholder: {'.implode('}, {', $unknown).'}. Use only the placeholders listed on this page.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $subject = trim(preg_replace('/\s+/', ' ', $validated['subject']) ?? $validated['subject']);
        EmailTemplates::save($key, $subject, trim($validated['body']));

        AuditLogger::log('UPDATE_EMAIL_TEMPLATE', "Updated the '{$key}' email template");

        return $this->backWithSuccess('Email template saved.');
    }

    public function reset(string $slug): RedirectResponse
    {
        $key = $this->eventKey($slug);
        EmailTemplates::reset($key);

        AuditLogger::log('RESET_EMAIL_TEMPLATE', "Reset the '{$key}' email template to the default");

        return $this->backWithSuccess('Email template reset to the default.');
    }

    /**
     * The email as recipients will see it, filled with sample values. Accepts
     * unsaved text so the editor can preview while typing.
     */
    public function preview(Request $request, string $slug): HttpResponse
    {
        $key = $this->eventKey($slug);
        $saved = EmailTemplates::get($key);

        $subject = mb_substr((string) $request->input('subject', $saved['subject']), 0, EmailTemplates::MAX_SUBJECT);
        $body = mb_substr((string) $request->input('body', $saved['body']), 0, EmailTemplates::MAX_BODY);
        $values = EmailTemplates::sampleValues($key);

        $mail = EmailTemplates::mailMessage(
            EmailTemplates::fill($subject, $values),
            EmailTemplates::paragraphs(EmailTemplates::fill($body, $values)) ?: ['(Empty message)'],
            $values['name'],
            url('/dashboard'),
        );

        return response((string) $mail->render())
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('X-Email-Subject', rawurlencode((string) $mail->subject))
            ->header('Cache-Control', 'no-store');
    }

    public static function slug(string $key): string
    {
        return str_replace('_', '-', $key);
    }

    private function eventKey(string $slug): string
    {
        $key = str_replace('-', '_', $slug);
        abort_unless(EmailTemplates::exists($key), 404);

        return $key;
    }
}
