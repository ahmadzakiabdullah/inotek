import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Settings, Sparkles, Paintbrush, Globe, LayoutGrid, Check } from 'lucide-react';
import { toast } from 'sonner';

interface SettingsProps {
    currentSettings: {
        system_name: string;
        global_font: string;
        primary_color: string;
        date_format: string;
        timezone: string;
    };
}

const fontList = [
    { value: 'Inter', label: 'Inter (Clean & Modern)' },
    { value: 'Roboto', label: 'Roboto (Neutral Sans)' },
    { value: 'Poppins', label: 'Poppins (Geometric & Friendly)' },
    { value: 'Outfit', label: 'Outfit (Sleek & Professional)' },
    { value: 'Plus Jakarta Sans', label: 'Plus Jakarta Sans (Modern Tech)' },
    { value: 'Instrument Sans', label: 'Instrument Sans (Premium Brand)' },
    { value: 'Lora', label: 'Lora (Elegant Serif)' },
    { value: 'Playfair Display', label: 'Playfair Display (High Contrast Serif)' },
];

const colorPresets = [
    { name: 'Default Indigo', hex: '#4f46e5' },
    { name: 'Emerald', hex: '#10b981' },
    { name: 'Royal Blue', hex: '#2563eb' },
    { name: 'Violet', hex: '#7c3aed' },
    { name: 'Amber', hex: '#d97706' },
    { name: 'Rose', hex: '#e11d48' },
    { name: 'Cyan', hex: '#0891b2' },
    { name: 'Orange', hex: '#ea580c' },
];

const dateFormats = [
    { value: 'd/m/Y', label: 'DD/MM/YYYY (e.g. 18/06/2026)' },
    { value: 'Y-m-d', label: 'YYYY-MM-DD (e.g. 2026-06-18)' },
    { value: 'd M Y', label: 'DD Month YYYY (e.g. 18 Jun 2026)' },
    { value: 'm/d/Y', label: 'MM/DD/YYYY (e.g. 06/18/2026)' },
];

const timezones = [
    { value: 'Asia/Kuala_Lumpur', label: 'Malaysia Time (Asia/Kuala_Lumpur)' },
    { value: 'Asia/Singapore', label: 'Singapore Time (Asia/Singapore)' },
    { value: 'Asia/Jakarta', label: 'Indonesia Western Time (Asia/Jakarta)' },
    { value: 'Asia/Tokyo', label: 'Japan Standard Time (Asia/Tokyo)' },
    { value: 'Asia/London', label: 'GMT/BST (Europe/London)' },
    { value: 'UTC', label: 'Coordinated Universal Time (UTC)' },
    { value: 'America/New_York', label: 'Eastern Time (America/New_York)' },
];

export default function Index({ currentSettings }: SettingsProps) {
    const { data, setData, post, processing, errors } = useForm({
        system_name: currentSettings.system_name,
        global_font: currentSettings.global_font,
        primary_color: currentSettings.primary_color,
        date_format: currentSettings.date_format,
        timezone: currentSettings.timezone,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/settings', {
            onSuccess: () => {
                toast.success('System settings updated successfully in real-time!');
            },
            onError: () => {
                toast.error('Failed to update system settings.');
            }
        });
    };

    return (
        <>
            <Head title="System Configuration" />

            <div className="space-y-6 max-w-4xl mx-auto">
                <div className="flex flex-col gap-1.5">
                    <h1 className="text-2xl font-bold tracking-tight lg:text-3xl flex items-center gap-2">
                        <Settings className="h-8 w-8 text-primary" />
                        System Settings
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Customize branding, primary interface theme color, global typography, and regional configurations.
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="grid gap-6 md:grid-cols-5">
                    {/* Settings Forms (3 cols) */}
                    <div className="md:col-span-3 space-y-6">
                        {/* Section 1: Core Branding */}
                        <Card className="border border-border/50 bg-card/60 backdrop-blur-sm shadow-md">
                            <CardHeader className="flex flex-row items-center gap-3 space-y-0 pb-3">
                                <LayoutGrid className="h-5 w-5 text-primary" />
                                <div>
                                    <CardTitle className="text-base font-semibold">Branding & Logo</CardTitle>
                                    <CardDescription>Configure basic identity elements of the portal.</CardDescription>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-1.5">
                                    <label className="text-sm font-semibold text-foreground">System name</label>
                                    <Input
                                        value={data.system_name}
                                        onChange={(e) => setData('system_name', e.target.value)}
                                        placeholder="e.g. INOTEK"
                                        maxLength={100}
                                        required
                                        className={errors.system_name ? 'border-destructive' : ''}
                                    />
                                    {errors.system_name && (
                                        <p className="text-xs text-destructive">{errors.system_name}</p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Section 2: Aesthetics (Font & Color) */}
                        <Card className="border border-border/50 bg-card/60 backdrop-blur-sm shadow-md">
                            <CardHeader className="flex flex-row items-center gap-3 space-y-0 pb-3">
                                <Paintbrush className="h-5 w-5 text-primary" />
                                <div>
                                    <CardTitle className="text-base font-semibold">Theme & Typography</CardTitle>
                                    <CardDescription>Customize colors and fonts across the platform.</CardDescription>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-5">
                                {/* Font Selection */}
                                <div className="space-y-1.5">
                                    <label className="text-sm font-semibold text-foreground">Global Font Family</label>
                                    <Select
                                        value={data.global_font}
                                        onValueChange={(val) => setData('global_font', val)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select Font" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {fontList.map((font) => (
                                                <SelectItem key={font.value} value={font.value}>
                                                    {font.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.global_font && (
                                        <p className="text-xs text-destructive">{errors.global_font}</p>
                                    )}
                                </div>

                                {/* Color Selection */}
                                <div className="space-y-3">
                                    <label className="text-sm font-semibold text-foreground">Primary Brand Color</label>
                                    
                                    {/* Color presets list */}
                                    <div className="grid grid-cols-4 gap-2">
                                        {colorPresets.map((preset) => (
                                            <button
                                                key={preset.hex}
                                                type="button"
                                                onClick={() => setData('primary_color', preset.hex)}
                                                className={`relative flex items-center justify-between p-2 rounded-md border text-[11px] font-medium transition-all duration-200 cursor-pointer ${
                                                    data.primary_color.toLowerCase() === preset.hex.toLowerCase()
                                                        ? 'border-foreground shadow-sm bg-muted/40'
                                                        : 'border-border/60 hover:bg-muted/10'
                                                }`}
                                            >
                                                <span className="flex items-center gap-1.5">
                                                    <span 
                                                        className="w-3 h-3 rounded-full border border-black/10 shrink-0" 
                                                        style={{ backgroundColor: preset.hex }} 
                                                    />
                                                    {preset.name}
                                                </span>
                                                {data.primary_color.toLowerCase() === preset.hex.toLowerCase() && (
                                                    <Check className="h-3 w-3 text-foreground shrink-0 ml-1" />
                                                )}
                                            </button>
                                        ))}
                                    </div>

                                    {/* Hex input & picker */}
                                    <div className="flex gap-2 items-center mt-2">
                                        <div className="relative flex-1">
                                            <Input
                                                value={data.primary_color}
                                                onChange={(e) => setData('primary_color', e.target.value)}
                                                placeholder="#HEXCODE"
                                                maxLength={7}
                                                required
                                                className={`pl-9 ${errors.primary_color ? 'border-destructive' : ''}`}
                                            />
                                            <span 
                                                className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 rounded-full border border-black/10" 
                                                style={{ backgroundColor: data.primary_color || '#4f46e5' }} 
                                            />
                                        </div>
                                        <input
                                            type="color"
                                            value={data.primary_color.match(/^#[0-9a-fA-F]{6}$/) ? data.primary_color : '#4f46e5'}
                                            onChange={(e) => setData('primary_color', e.target.value)}
                                            className="w-10 h-10 p-0 border border-border rounded-md cursor-pointer bg-transparent"
                                        />
                                    </div>
                                    {errors.primary_color && (
                                        <p className="text-xs text-destructive">{errors.primary_color}</p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Section 3: Localization */}
                        <Card className="border border-border/50 bg-card/60 backdrop-blur-sm shadow-md">
                            <CardHeader className="flex flex-row items-center gap-3 space-y-0 pb-3">
                                <Globe className="h-5 w-5 text-primary" />
                                <div>
                                    <CardTitle className="text-base font-semibold">Regional & Localisation</CardTitle>
                                    <CardDescription>Adjust dates, timezones, and regional formats.</CardDescription>
                                </div>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-1.5">
                                    <label className="text-sm font-semibold text-foreground">Date Format</label>
                                    <Select
                                        value={data.date_format}
                                        onValueChange={(val) => setData('date_format', val)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select Date Format" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {dateFormats.map((df) => (
                                                <SelectItem key={df.value} value={df.value}>
                                                    {df.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.date_format && (
                                        <p className="text-xs text-destructive">{errors.date_format}</p>
                                    )}
                                </div>

                                <div className="space-y-1.5">
                                    <label className="text-sm font-semibold text-foreground">Default Timezone</label>
                                    <Select
                                        value={data.timezone}
                                        onValueChange={(val) => setData('timezone', val)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select Timezone" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {timezones.map((tz) => (
                                                <SelectItem key={tz.value} value={tz.value}>
                                                    {tz.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.timezone && (
                                        <p className="text-xs text-destructive">{errors.timezone}</p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Save Button */}
                        <Button
                            type="submit"
                            disabled={processing}
                            className="w-full shadow-lg transition-all duration-200 hover:shadow-primary/20 flex items-center justify-center gap-2"
                        >
                            {processing ? 'Saving Configurations...' : 'Save Settings'}
                        </Button>
                    </div>

                    {/* Preview Panel (2 cols) */}
                    <div className="md:col-span-2">
                        <Card className="border border-border/50 bg-card/40 backdrop-blur-sm h-full flex flex-col">
                            <CardHeader>
                                <CardTitle className="text-lg font-semibold flex items-center gap-2">
                                    <Sparkles className="h-5 w-5 text-primary" />
                                    Live Preview
                                </CardTitle>
                                <CardDescription>How the UI adjustments look in real-time.</CardDescription>
                            </CardHeader>
                            <CardContent className="flex-1 flex flex-col justify-center p-6 space-y-6">
                                {/* Sample System Card */}
                                <div 
                                    className="border border-border/50 rounded-lg p-5 bg-card/60 shadow-sm space-y-4"
                                    style={{ fontFamily: `'${data.global_font}', sans-serif` }}
                                >
                                    <div className="flex justify-between items-center pb-2 border-b border-border/30">
                                        <span className="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                            {data.system_name || 'System Name'}
                                        </span>
                                        <span className="text-[10px] bg-muted px-2 py-0.5 rounded font-mono text-muted-foreground">
                                            Preview Mode
                                        </span>
                                    </div>

                                    <div className="space-y-1">
                                        <h4 className="text-sm font-bold text-foreground">
                                            Branding Styles Example
                                        </h4>
                                        <p className="text-xs text-muted-foreground leading-relaxed">
                                            This typography dynamically showcases the selected Google Font: <strong>{data.global_font}</strong>.
                                        </p>
                                    </div>

                                    {/* Primary Button Example */}
                                    <button 
                                        type="button"
                                        className="w-full text-white text-xs font-semibold py-2 px-4 rounded-md transition-all duration-200 shadow-sm"
                                        style={{ backgroundColor: data.primary_color || '#4f46e5' }}
                                    >
                                        Primary Theme Button
                                    </button>

                                    {/* Outline Button Example */}
                                    <button 
                                        type="button"
                                        className="w-full bg-transparent border text-xs font-semibold py-2 px-4 rounded-md hover:bg-muted/10 transition-all duration-200"
                                        style={{ borderColor: data.primary_color || '#4f46e5', color: data.primary_color || '#4f46e5' }}
                                    >
                                        Secondary Theme Button
                                    </button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </form>
            </div>
        </>
    );
}

Index.layout = (page: React.ReactNode) => {
    // Dynamically retrieve breadcrumbs layout or wrap in standard dashboard
    const Layout = require('@/components/layout/sidebar/app-sidebar').AppSidebar;
    return (
        <div className="flex h-screen overflow-hidden bg-background">
            {page}
        </div>
    );
};

Index.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: '/dashboard',
        },
        {
            title: 'System Settings',
            href: '/admin/settings',
        },
    ],
};
