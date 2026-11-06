import { Button } from '@/components/ui/button';
export default function DraftStatus({
    status,
    error,
    onTakeover,
    onReload,
    onDiscard,
}: {
    status: string;
    error: string;
    onTakeover: () => void;
    onReload: () => void;
    onDiscard?: () => void;
}) {
    return (
        <div className="space-y-2 text-sm" role="status">
            <p>
                {status === 'saved'
                    ? 'Cart saved on this browser'
                    : status === 'read-only'
                      ? 'Another tab owns this cart'
                      : status === 'recovering'
                        ? 'Saved payment recording needs recovery'
                        : status === 'storage-error'
                          ? 'Draft storage needs attention'
                          : status === 'saving'
                            ? 'Saving cart…'
                            : 'Loading saved cart…'}
            </p>
            {error && <p role="alert">{error}</p>}
            {status === 'read-only' && (
                <Button
                    className="min-h-11"
                    variant="outline"
                    onClick={onTakeover}
                >
                    Take over
                </Button>
            )}
            {onDiscard && (
                <Button
                    className="min-h-11"
                    variant="outline"
                    onClick={onDiscard}
                >
                    Discard damaged editable cart
                </Button>
            )}
            {(error || status === 'read-only') && (
                <Button
                    className="min-h-11"
                    variant="outline"
                    onClick={onReload}
                >
                    Reload saved cart
                </Button>
            )}
        </div>
    );
}
