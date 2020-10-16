<?php

namespace App\Commands;

use DateTime;
use Exception;
use JiraRestApi\Board\BoardService;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class BuildOutcome
{
    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @param OutputInterface $output
     * @param null $dateStr
     * @param null $boardId
     */
    public function run(OutputInterface $output, $dateStr = null, $boardId = null)
    {
        $this->output = $output;

        try {
            $outcomes = $this->getOutcomes($dateStr, $boardId);
            $this->showOutcomes($outcomes);

        } catch (Throwable $e) {
            $this->output->writeln($e->getMessage());
        }
    }

    /**
     * @param $targetDateStr
     * @param $boardId
     * @return array
     * @throws Exception
     */
    private function getOutcomes($targetDateStr, $boardId): array
    {
        $targetDate   = new DateTime($targetDateStr);
        $boardService = new BoardService();
        $issues       = $boardService->getBoardIssues(
            $boardId ?: $_ENV['DEFAULT_BOARD_ID'],
            [
                'maxResults' => 500,
                'expand'     => 'changelog',
                'jql'        => urlencode(
                    sprintf(
                        'updated >= "%s" & status != Closed & status != Done',
                        $targetDate->format('Y/m/d 00:00')
                    )
                ),
            ]
        );

        $outcomes = [];
        foreach ($issues as $issue) {
            $key                    = $issue->key;
            $status                 = $issue->fields->status->name;
            $summary                = $issue->fields->summary;
            $currentStatus          = null;
            $updatedCurrentStatusAt = new DateTime('07.07.1989');
            $updatedAt              = $issue->fields->updated;
            $url                    = 'https://alycecom.atlassian.net/browse/' . $key;

            foreach ($issue->changelog->histories as $history) {
                $innerUpdatedAt = new DateTime($history->created);
                if ($innerUpdatedAt->format("ymd") !== $targetDate->format("ymd")) {
                    continue;
                }
                foreach ($history->items as $item) {
                    if ($item->field !== 'Current status') {
                        continue;
                    }

                    if ($innerUpdatedAt < $updatedCurrentStatusAt) {
                        continue;
                    }


                    $updatedCurrentStatusAt = $innerUpdatedAt;
                    $currentStatus          = $item->toString;
                }
            }

            if ($currentStatus) {
                $a          = 1;
                $outcomes[]         = compact(
                    'key',
                    'status',
                    'currentStatus',
                    'summary',
                    'updatedCurrentStatusAt',
                    'updatedAt',
                    'url'
                );
            }
        }

        return $outcomes;
    }

    /**
     * @param array $outcomes
     */
    private function showOutcomes(array $outcomes)
    {
        if (!$outcomes) {
            return;
        }

        $this->output->writeln('*Daily sync outcome*');
        foreach ($outcomes as $outcome) {
            // remove junk
            $summary = preg_replace('/as.+i want to/i', '', $outcome['summary']);
            $summary = implode(' ', array_slice(str_word_count($summary, 2), 0, 6));

            $this->output->writeln(sprintf(
                "<%s|%s> [%s] %s... - %s",
                $outcome['url'],
                $outcome['key'],
                $outcome['status'],
                $summary,
                $outcome['currentStatus']
            ));
        }
    }
}