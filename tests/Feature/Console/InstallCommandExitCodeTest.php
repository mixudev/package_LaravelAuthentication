<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Feature\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Output\BufferedOutput;
use Vendor\LaravelAuthentication\Console\InstallCommand;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * authentication:install must propagate failures from every sub-command it
 * drives. A silent SUCCESS after a failed publish or migration leaves the host
 * half-installed while reporting success, and operators then trust protection
 * that was never wired up.
 */
class InstallCommandExitCodeTest extends TestCase
{
    /** @var list<string> */
    private array $preMigrationFiles = [];
    private bool $preConfigExists = false;
    private ?string $preAppCss = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->preMigrationFiles = array_values(array_filter((array) glob(database_path('migrations') . '/*.php')));
        $this->preConfigExists = is_file(config_path('authentication.php'));
        $this->preAppCss = is_file(resource_path('css/app.css'))
            ? (string) file_get_contents(resource_path('css/app.css'))
            : null;
    }

    protected function tearDown(): void
    {
        if (! $this->preConfigExists && is_file(config_path('authentication.php'))) {
            @unlink(config_path('authentication.php'));
        }

        foreach ((array) glob(database_path('migrations') . '/*.php') as $file) {
            if (! in_array($file, $this->preMigrationFiles, true)) {
                @unlink($file);
            }
        }

        if ($this->preAppCss !== null) {
            file_put_contents(resource_path('css/app.css'), $this->preAppCss);
        }

        parent::tearDown();
    }

    public function test_install_fails_when_config_publish_fails(): void
    {
        $exit = $this->runInstall(failOn: ['vendor:publish#0']);

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertFileDoesNotExist(config_path('authentication.php'));
    }

    public function test_install_fails_when_migration_publish_fails(): void
    {
        $exit = $this->runInstall(failOn: ['vendor:publish#1']);

        $this->assertSame(Command::FAILURE, $exit);
    }

    public function test_install_fails_when_migration_execution_fails(): void
    {
        $exit = $this->runInstall(failOn: ['migrate#0'], options: ['--migrate' => true]);

        $this->assertSame(Command::FAILURE, $exit);
    }

    public function test_install_succeeds_when_every_step_succeeds(): void
    {
        $exit = $this->runInstall(failOn: [], options: ['--migrate' => true]);

        $this->assertSame(Command::SUCCESS, $exit);
    }

    /**
     * @param list<string> $failOn Entries of the form "command#index" where
     *                             index is the zero-based invocation counter.
     * @param array<string, bool> $options
     */
    private function runInstall(array $failOn, array $options = []): int
    {
        $command = new class extends InstallCommand {
            /** @var list<string> */
            public array $failOn = [];

            /** @var array<string, int> */
            public array $seen = [];

            public function call($command, array $arguments = [])
            {
                $index = ($this->seen[$command] = ($this->seen[$command] ?? 0) + 1) - 1;

                if (in_array($command . '#' . $index, $this->failOn, true)) {
                    return 1;
                }

                return 0;
            }
        };

        $command->failOn = $failOn;
        $command->setLaravel($this->app);

        $input = new ArrayInput($options);
        $input->setInteractive(false);

        return $command->run($input, new OutputStyle($input, new BufferedOutput()));
    }
}
