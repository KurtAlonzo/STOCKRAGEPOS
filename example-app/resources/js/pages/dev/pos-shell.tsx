export default function PosShell() {
    return (
        <div className="space-y-4">
            <h1 className="text-2xl font-semibold">Checkout layout preview</h1>
            <p>Sample data only. No sale or payment can be recorded.</p>
            {Array.from({ length: 24 }, (_, i) => (
                <div key={i} className="rounded-md border p-6">
                    Example product {i + 1} — long product name for layout
                    verification
                </div>
            ))}
        </div>
    );
}
