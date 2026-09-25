<?php

namespace Epesi\Modules\Shoutbox\Filament\Concerns;

use Livewire\Attributes\Locked;

/**
 * Polling that redraws only when there's something new, as Epesi's
 * refresh.php did. The component says what "new" means in fingerprint().
 */
trait RedrawsWhenChanged
{
    /** The fingerprint of what was last drawn — see poll(). */
    #[Locked]
    public string $shown = '';

    /**
     * Called every 10 seconds by the view. Nothing new → no redraw, so the
     * reply is a few bytes.
     */
    public function poll(): void
    {
        if ($this->fingerprint() === $this->shown) {
            $this->skipRender();
        }
    }

    /** Every redraw, whatever caused it, remembers what it drew. */
    public function rendering(): void
    {
        $this->shown = $this->fingerprint();
    }

    abstract protected function fingerprint(): string;
}
