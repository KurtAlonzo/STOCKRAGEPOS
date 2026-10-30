import { Form, Head } from '@inertiajs/react';
import { update } from '@/routes/teams';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import type { Team } from '@/types';
export default function StoreEdit({ team }: { team: Team }) {
    return (
        <div className="space-y-6">
            <Head title="Store settings" />
            <h1 className="text-2xl font-semibold">Store settings</h1>
            <Form {...update.form(team.slug)}>
                {({ processing, errors }) => (
                    <div className="space-y-4">
                        <Label htmlFor="name">Store name</Label>
                        <Input
                            id="name"
                            name="name"
                            defaultValue={team.name}
                            aria-describedby="name-error"
                        />
                        <div id="name-error">
                            <InputError message={errors.name} />
                        </div>
                        <Button disabled={processing}>Save store</Button>
                    </div>
                )}
            </Form>
        </div>
    );
}
