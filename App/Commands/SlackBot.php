<?php

namespace App\Commands;

use App\Service\Db;
use DateTime;
use Exception;
use PhpSlackBot\Command\BaseCommand;
use Symfony\Component\Console\Output\BufferedOutput;
use PhpSlackBot\Bot;
use Wheeler\Fortune\Fortune;

class SlackBot
{
    /**
     * @throws Exception
     */
    public function run()
    {
        while (1) { // 8h disconnect bug
            $bot = new Bot();
            $bot->setToken($_ENV['SLACK_API_TOKEN']);

            foreach ($this->getCommands() as $object) {
                $bot->loadCommand($object);
            }

            $bot->run();
        }
    }

    /**
     * @return array
     */
    private function getCommands(): array
    {
        return [
            // help
            new class extends BaseCommand {
                protected function configure()
                {
                    $this->setName('help');
                }

                protected function execute($message, $context)
                {
                    $this->send($this->getCurrentChannel(), null,
                        "Hey There!\n" .
                        "I gather all \"Current Status\" changes from a jira board on a given day(default is today)\n" .
                        "And print it here, so you don't have to!\n" .
                        "To start you will need to provide me with your jira board id (look at the address bar)\n" .
                        "Do it by typing \"*set board x*\", where x is the id\n" .
                        "After that type \"*get outcome y*\" where y is the day in any format, no date = today\n" .
                        "Note: you can also say dynamic dates like \"*get outcome yesterday*\", <https://www.php.net/manual/en/datetime.formats.date.php|full list>\n" .
                        "Limitations: board must be visible to: mikael.ts@alyce.com\n" .
                        "Also type \"fortune\" to get a fortune cookie\n" .
                        "Have a great one!\n"
                    );
                }

            },
            // set board
            new class extends BaseCommand {
                protected function configure()
                {
                    $this->setName('set board');
                }

                protected function execute($data, $context)
                {
                    $boardId   = preg_replace('/<.+>/', '', $data['text']);
                    $boardId   = preg_replace('/[\sa-z]/i', '', $boardId);
                    $prevEntry = Db::getDb()->get('boards', '*', ['user' => $data['user']]);

                    if ($prevEntry) { // todo boards service?
                        Db::getDb()->update('boards', [
                            'board_id'   => $boardId,
                            'updated_at' => (new DateTime())->format('d.m.Y H:i:s'),
                        ], ['user' => $data['user']]);
                    } else {
                        Db::getDb()->insert('boards', [
                            'user'       => $data['user'],
                            'board_id'   => $boardId,
                            'created_at' => (new DateTime())->format('d.m.Y H:i:s'),
                            'updated_at' => (new DateTime())->format('d.m.Y H:i:s'),
                        ]);
                    }

                    $this->send($this->getCurrentChannel(), null,
                        "Board set to {$boardId}"
                    );
                }
            },
            // frn
            new class extends BaseCommand {
                protected function configure()
                {
                    $this->setName('fortune');
                }

                protected function execute($data, $context)
                {
                    $this->send($this->getCurrentChannel(), null, Fortune::make());
                }
            },
            // get outcome
            new class extends BaseCommand {
                protected function configure()
                {
                    $this->setName('get outcome');
                }

                protected function execute($data, $context)
                {
                    $prevEntry = Db::getDb()->get('boards', '*', ['user' => $data['user']]);
                    if (!$prevEntry) {
                        $this->send($this->getCurrentChannel(), null, 'Please set board id first, say "help" to learn more');
                        return;
                    }

                    $boardId = $prevEntry['board_id'];
                    $date    = preg_replace('/<.+>/', '', $data['text']);
                    $date    = preg_replace('/get outcome| /i', '', $date) ?: null;

                    $bufferOutput = new BufferedOutput;
                    $outcome      = new BuildOutcome();
                    $outcome->run($bufferOutput, $date, $boardId);
                    $result = $bufferOutput->fetch();

                    if (!$result) {
                        $this->send($this->getCurrentChannel(), null, "No outcome for that day.");
                        return;
                    }

                    $this->send($this->getCurrentChannel(), null, $result);
                }
            },
        ];
    }
}