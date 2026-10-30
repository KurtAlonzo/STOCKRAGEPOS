import { Head, usePage } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
export default function Dashboard() {
    const { currentTeam } = usePage().props;
    return (
        <div className="space-y-8 p-4 md:p-8">
            <Head title="Dashboard" />
            <header>
                <p className="text-sm text-muted-foreground">
                    {currentTeam?.name}
                </p>
                <h1 className="text-3xl font-semibold">Dashboard</h1>
            </header>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {[
                    "Today's sales",
                    'Transactions',
                    'Catalog items',
                    'Low-stock items',
                ].map((title) => (
                    <Card key={title}>
                        <CardHeader>
                            <CardTitle>{title}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-semibold">—</p>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Not connected yet
                            </p>
                        </CardContent>
                    </Card>
                ))}
            </div>
            <Card>
                <CardHeader>
                    <CardTitle>Sales and stock overview</CardTitle>
                </CardHeader>
                <CardContent className="text-muted-foreground">
                    Sales and inventory data are not connected yet.
                </CardContent>
            </Card>
        </div>
    );
}
