<?php

namespace Torann\MediaSort\Concerns;

use Torann\MediaSort\Manager;

trait HasQueuing
{
    /**
     * Determine if the attachment is queueable.
     *
     * @return bool
     */
    public function isQueueable(): bool
    {
        return $this->config('queueable', false) == true;
    }

    /**
     * Determine if the attachment is being processed.
     *
     * @return bool
     */
    public function isQueued(): bool
    {
        if ($this->isQueueable()) {
            return ((int) $this->getAttribute('queue_state')) > Manager::QUEUE_DONE;
        }

        return false;
    }

    /**
     * Determine if the attachment is in the given queue state.
     *
     * @param string $state
     *
     * @return bool
     **/
    public function isQueueState(string $state): bool
    {
        if ($this->isQueueable() === false) {
            return false;
        }

        // Set state value from constant
        $state = strtoupper($state);
        $state = defined("\\Torann\\MediaSort\\Manager::QUEUE_{$state}")
            ? constant("\\Torann\\MediaSort\\Manager::QUEUE_{$state}")
            : null;

        return $state !== null && ((int) $this->getAttribute('queue_state')) === $state;
    }

    /**
     * Get queue state text.
     *
     * @return string
     */
    public function getQueuedStateText(): string
    {
        return match ((int) $this->getAttribute('queue_state')) {
            Manager::QUEUE_NA => '',
            Manager::QUEUE_DONE => 'done',
            Manager::QUEUE_WAITING => 'waiting',
            Manager::QUEUE_WORKING => 'working',
            default => 'unknown',
        };
    }

    /**
     * Get the queued file path.
     *
     * @return string
     */
    public function getQueuedFilePath(): string
    {
        return $this->getInterpolator()
            ->interpolate(
                $this->joinPaths(
                    $this->config('queue_path'),
                    $this->getAttribute('queued_file')
                )
            );
    }

    /**
     * Use the model's connecting and table to quickly update the queue state and
     * bypass the save event in the model to prevent an event loop.
     *
     * @param int $state
     *
     * @return void
     */
    public function updateQueueState(int $state)
    {
        if ($this->isQueueable()) {
            $this->getModel()
                ->getConnection()
                ->table($this->getModel()->getTable())
                ->where($this->getModel()->getQualifiedKeyName(), $this->getModel()->getKey())
                ->update([
                    "{$this->name}_queue_state" => $state,
                ]);
        }
    }

    /**
     * Generates the loading url if the attachment hasn't been processed.
     *
     * @param string $style
     *
     * @return string
     */
    protected function queueUrl(string $style = ''): string
    {
        // Determine which dynamic image to display
        $key = match ((int) $this->getAttribute('queue_state')) {
            self::QUEUE_WAITING => 'waiting_url',
            self::QUEUE_FAILED => 'failed_url',
            default => 'loading_url',
        };

        if ($this->config($key)) {
            $url = $this->getInterpolator()->interpolate(
                $this->config($key), $style
            );

            return parse_url($url, PHP_URL_HOST) ? $url : $this->config('prefix_url') . $url;
        }

        return '';
    }
}
