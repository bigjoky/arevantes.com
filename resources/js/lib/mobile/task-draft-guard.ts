/** Ephemeral guard for one task sheet. No draft values or browser storage. */
export class TaskDraftGuard {
    private submitting = false;
    private pending: (() => void) | null = null;

    private state: () => { dirty: boolean; processing: boolean };
    private confirm: (open: boolean) => void;
    private reset: () => void;

    constructor(
        state: () => { dirty: boolean; processing: boolean } = () => ({
            dirty: false,
            processing: false,
        }),
        confirm: (open: boolean) => void = () => {},
        reset: () => void = () => {},
    ) {
        this.state = state;
        this.confirm = confirm;
        this.reset = reset;
    }

    connect(
        state: () => { dirty: boolean; processing: boolean },
        confirm: (open: boolean) => void,
        reset: () => void,
    ) {
        this.state = state;
        this.confirm = confirm;
        this.reset = reset;
    }

    busy() {
        return this.submitting || this.state().processing;
    }

    beginSubmit() {
        if (this.busy() || this.pending) {
            return false;
        }

        this.submitting = true;

        return true;
    }

    finishSubmit() {
        this.submitting = false;
    }

    requestClose(close: () => void) {
        if (this.busy()) {
            return 'busy';
        }

        if (this.pending) {
            return 'confirm';
        }

        if (!this.state().dirty) {
            close();

            return 'closed';
        }

        this.pending = close;
        this.confirm(true);

        return 'confirm';
    }

    keepEditing() {
        this.pending = null;
        this.confirm(false);
    }

    discard() {
        if (this.busy() || !this.pending) {
            return;
        }

        const close = this.pending;
        this.pending = null;
        this.reset();
        this.confirm(false);
        close();
    }

    saved(close: () => void) {
        this.pending = null;
        this.submitting = false;
        this.reset();
        this.confirm(false);
        close();
    }
}
