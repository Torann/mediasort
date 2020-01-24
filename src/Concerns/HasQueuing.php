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
    public function isQueueable()
    {
        return $this->config('queueable', false) == true;
    }

    /**
     * Determine if the attachment is being processed.
     *
     * @return bool
     */
    public function isQueued()
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
    public function isQueueState($state): bool
    {
        if ($this->isQueueable() === false) {
            return false;
        }

        // Set state value from constant
        $state = strtoupper($state);
        $state = defined("\\Torann\\MediaSort\\Manager::QUEUE_{$state}")
            ? constant("\\Torann\\MediaSort\\Manager::QUEUE_{$state}")
            : null;

        return $state !== null && ((int) $this->getAttribute('queue_state')) === $state ? true : false;
    }

    /**
     * Get queue state text.
     *
     * @return string
     */
    public function getQueuedStateText()
    {
        switch ((int) $this->getAttribute('queue_state')) {
            case Manager::QUEUE_NA:
                return '';
            case Manager::QUEUE_DONE:
                return 'done';
            case Manager::QUEUE_WAITING:
                return 'waiting';
            case Manager::QUEUE_WORKING:
                return 'working';
            default:
                return 'unknown';
        }
    }

    /**
     * Get the queued file path.
     *
     * @return string
     */
    public function getQueuedFilePath()
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
    protected function queueUrl($style = '')
    {
        // Determine which dynamic image to display
        switch ((int) $this->getAttribute('queue_state')) {
            case self::QUEUE_WAITING:
                $key = 'waiting_url';
                break;
            case self::QUEUE_FAILED:
                $key = 'failed_url';
                break;
            default:
                $key = 'loading_url';
                break;
        }

        if ($this->config($key)) {
            $url = $this->getInterpolator()->interpolate(
                $this->config($key), $style
            );

            return parse_url($url, PHP_URL_HOST) ? $url : $this->config('prefix_url') . $url;
        }

        return '';
    }
}
