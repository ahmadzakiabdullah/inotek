export default function AppLogo() {
    return (
        <div className="flex items-center gap-2">
            <img src="/logo.svg" alt="Logo UTeM" className="h-8 w-auto" />
            <div className="grid text-left text-sm">
                <span className="truncate leading-tight font-semibold">INOTEK</span>
            </div>
        </div>
    );
}
