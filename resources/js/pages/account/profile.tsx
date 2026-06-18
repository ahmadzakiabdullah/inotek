import { Link, Head } from '@inertiajs/react';
import { Settings } from 'lucide-react';
import { CompleteYourProfileCard } from './complete-your-profile';
import { Button } from '@/components/ui/button';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { CardSkills } from './card-skills';
import { LatestActivity } from './latest-activity';
import { AboutMe } from './about-me';
import { Connections } from './connections';
import { ProfileCard } from './profile-card';

export default function ProfilePage() {
    return (
        <>
            <Head title="Profile" />
            <div className="space-y-4">
                <div className="flex flex-row items-center justify-between">
                    <h1 className="text-xl font-bold tracking-tight lg:text-2xl">
                        Profile Page
                    </h1>
                    <div className="flex items-center space-x-2">
                        <Button asChild>
                            <Link href="/settings/profile">
                                <Settings className="mr-2 h-4 w-4" />
                                Settings
                            </Link>
                        </Button>
                    </div>
                </div>

                <Tabs defaultValue="overview">
                    <TabsList>
                        <TabsTrigger value="overview">Overview</TabsTrigger>
                        <TabsTrigger value="projects">Projects</TabsTrigger>
                        <TabsTrigger value="activities">Activities</TabsTrigger>
                        <TabsTrigger value="members">Members</TabsTrigger>
                    </TabsList>
                </Tabs>

                <div className="grid gap-4 xl:grid-cols-3">
                    <div className="space-y-4 xl:col-span-1">
                        <ProfileCard />
                        <CompleteYourProfileCard />
                        <CardSkills />
                    </div>
                    <div className="space-y-4 xl:col-span-2">
                        <LatestActivity />
                        <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                            <AboutMe />
                            <Connections />
                        </div>
                    </div>
                </div>
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
