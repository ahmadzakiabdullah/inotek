import { Link, Head } from '@inertiajs/react';
import { Settings } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ProfileCard } from './profile-card';

export default function ProfilePage() {
    return (
        <>
            <Head title="Profile" />
            <div className="mx-auto w-full max-w-3xl space-y-6">
                <div className="flex items-end justify-between gap-4">
                    <div className="space-y-1">
                        <p className="text-sm font-medium text-muted-foreground">
                            Account
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Profile
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            View your account information and participant details.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href="/dashboard/settings/profile">
                            <Settings className="mr-2 h-4 w-4" />
                            Edit profile
                        </Link>
                    </Button>
                </div>

                <ProfileCard />
            </div>
        </>
    );
}

ProfilePage.layout = {
    breadcrumbs: [
        {
            title: 'Profile',
            href: '/account/profile',
        },
    ],
};
