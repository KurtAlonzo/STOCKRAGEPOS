import { Head, Link } from '@inertiajs/react';
import CreateTeamModal from '@/components/create-team-modal';
import { Button } from '@/components/ui/button';
import { edit } from '@/routes/teams';
import type { Team } from '@/types';
export default function Stores({ teams }: { teams: Team[] }) {
    return (
        <div className="space-y-6">
            <Head title="Stores" />
            <h1 className="text-2xl font-semibold">Stores</h1>
            <CreateTeamModal>
                <Button>New store</Button>
            </CreateTeamModal>
            {teams.map((team) => (
                <div
                    key={team.id}
                    className="flex min-w-0 flex-wrap justify-between gap-4 rounded-md border p-4"
                >
                    <span className="break-all">{team.name}</span>
                    <Link href={edit(team.slug)}>Manage store</Link>
                </div>
            ))}
        </div>
    );
}
