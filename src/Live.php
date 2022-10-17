<?php

declare(strict_types=1);

namespace Termwind;

use Closure;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Termwind\Events\RefreshEvent;

/**
 * @internal
 */
final class Live
{
    private ConsoleSectionOutput $section;
    private $actualTime = 0;
    private $sttyMode = '';

    /**
     * Creates a new Live instance.
     */
    public function __construct(
        private Terminal $terminal,
        private ConsoleOutput $output,
        private HtmlRenderer $renderer,
        private Closure $htmlResolver
    ) {
        $this->section = $this->output->section();

        pcntl_async_signals(true);
        pcntl_signal(SIGINT, function () {
            $this->shutdown();
            exit;
        });

        pcntl_signal(SIGTERM, function () {
            $this->shutdown();
            exit;
        });
    }

    /**
     * Clears the html.
     */
    public function clear(): void
    {
        $this->section->clear();
    }

    /**
     * Renders the live html.
     */
    public function render(): bool
    {
        $html = call_user_func($this->htmlResolver, $refreshingEvent = new RefreshEvent(), $mouse = [0, 0], false);

        $html = $this->renderer->parse((string) $html);

        $this->section->write($html->toString());

        return true;
    }

    /**
     * Clears the html, and re-renders the live html.
     */
    public function refresh(): void
    {
        $this->section->clear();

        $this->render();
    }

    /**
     * Creates a new Refreshable Live instance.
     *
     * @return $this
     */
    public function refreshEvery(int $seconds): self
    {
        $stdin = \defined('STDIN') ? \STDIN : fopen('php://input', 'r+');
        $this->sttyMode = shell_exec('stty -g');


        shell_exec('stty -echo -icanon');
        $this->output->write("\e[?1003h\e[?1015h\e[?1006h");

        while (true) {
            $key = fread($stdin, 16);

            $mouse = $this->getMouseMovement($key);
            $click = $this->getMouseClick($key);

            if (! $click && ! $this->shouldRefresh($mouse, $seconds)) {
                continue;
            }

            $html = call_user_func($this->htmlResolver, $refreshingEvent = new RefreshEvent(), $mouse, $click);

            if ($refreshingEvent->stop) {
                break;
            }

            $html = $this->renderer->parse(
                (string) $html,
                $mouse ?? [0, 0],
                $click ?? false
            );

            $this->section->clear();
            $this->section->write($html->toString());
        }

        $this->shutdown();

        return $this;
    }

    private function shutdown()
    {
        $this->output->write("\e[?1000l");
        shell_exec('stty echo');
    }

    private function shouldRefresh($mouse, $seconds)
    {
        if (! is_null($mouse)) {
            return true;
        }

        if (time() - $this->actualTime >= $seconds) {
            $this->actualTime = time();
            return true;
        }

        return false;
    }

    private function getMouseMovement(string $key)
    {
        $mouse = explode('<', $key) ?? [];
        $mouse = explode(';', $mouse[1] ?? '');

        $isMoving = ($mouse[0] ?? null) === '35';

        if (! $isMoving) {
            return null;
        }

        $y = $mouse[2] ?? '';
        $y = isset($mouse[2]) ? substr($mouse[2] ?? '', 0, -1) : null;
        $x = $mouse[1] ?? null;

        return [$y, $x];
    }

    private function getMouseClick(string $key)
    {
        $mouse = explode('<', $key) ?? [];
        $mouse = explode(';', $mouse[1] ?? '');

        $first = $mouse[0] ?? '';
        $second = substr($mouse[2] ?? '', -1);

        return $first === '0' && $second === 'M';
    }
}
