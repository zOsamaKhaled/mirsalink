export default function Heading({
    title,
    description,
    variant = 'default',
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
}) {
    return (
        <header
            className={
                variant === 'small'
                    ? 'flex flex-col gap-1'
                    : 'mb-8 flex flex-col gap-1.5'
            }
        >
            <h2
                className={
                    variant === 'small'
                        ? 'text-title font-bold'
                        : 'text-display font-extrabold'
                }
            >
                {title}
            </h2>
            {description && (
                <p className="text-body-sm text-muted-foreground">
                    {description}
                </p>
            )}
        </header>
    );
}
