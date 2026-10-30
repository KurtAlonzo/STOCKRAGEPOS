import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
export function UnsavedChangesPrompt({
    open,
    keep,
    discard,
}: {
    open: boolean;
    keep: () => void;
    discard: () => void;
}) {
    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                if (!value) keep();
            }}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Discard unsaved changes?</DialogTitle>
                    <DialogDescription>
                        Your changes have not been saved. Keep editing or
                        discard them before leaving.
                    </DialogDescription>
                </DialogHeader>
                <div className="flex flex-wrap gap-2">
                    <Button type="button" onClick={keep}>
                        Keep editing
                    </Button>
                    <Button type="button" variant="outline" onClick={discard}>
                        Discard changes
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
