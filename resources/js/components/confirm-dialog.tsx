import { router } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';

/**
 * Asks before deleting, then sends the DELETE and closes. `url` null keeps
 * it closed.
 */
export function ConfirmDeleteDialog({
    url,
    title,
    body,
    confirmLabel,
    onClose,
}: {
    url: string | null;
    title: string;
    body?: string;
    confirmLabel: string;
    onClose: () => void;
}) {
    const { t } = useTranslations();
    const [processing, setProcessing] = useState(false);

    const confirm = () => {
        if (!url) {
            return;
        }

        router.delete(url, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                onClose();
            },
        });
    };

    return (
        <Dialog open={url !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription className={body ? undefined : 'sr-only'}>
                    {body ?? title}
                </DialogDescription>
                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            {t('common.cancel')}
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        variant="destructive"
                        disabled={processing}
                        onClick={confirm}
                    >
                        {processing && <Spinner />}
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
