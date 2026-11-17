import { Medal } from 'lucide-react';
import { Badge } from '@/components/ui/badge';

const MEDAL_STYLES: Record<string, string> = {
    gold: 'bg-yellow-500 text-slate-900 hover:bg-yellow-500',
    silver: 'bg-slate-300 text-slate-900 hover:bg-slate-300',
    bronze: 'bg-amber-600 text-white hover:bg-amber-600',
};

export function AwardBadge({ award }: { award: string | null | undefined }) {
    if (!award) {
        return null;
    }

    const medal = MEDAL_STYLES[award.toLowerCase()];

    if (medal) {
        return (
            <Badge
                className={`gap-1 border-none px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase ${medal}`}
            >
                <Medal className="h-3 w-3" /> {award}
            </Badge>
        );
    }

    return (
        <Badge
            variant="outline"
            className="border-primary/30 text-[10px] font-bold tracking-wider text-primary uppercase"
        >
            {award}
        </Badge>
    );
}
