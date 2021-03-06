<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Command;

use Contao\CoreBundle\Command\InstallCommand;
use Contao\CoreBundle\Tests\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class InstallCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        $fs = new Filesystem();
        $fs->remove($this->getTempDir().'/assets/css');
        $fs->remove($this->getTempDir().'/assets/images');
        $fs->remove($this->getTempDir().'/assets/images_test');
        $fs->remove($this->getTempDir().'/assets/js');
        $fs->remove($this->getTempDir().'/files_test');
        $fs->remove($this->getTempDir().'/system/cache');
        $fs->remove($this->getTempDir().'/system/config');
        $fs->remove($this->getTempDir().'/system/initialize.php');
        $fs->remove($this->getTempDir().'/system/modules/.gitignore');
        $fs->remove($this->getTempDir().'/system/themes');
        $fs->remove($this->getTempDir().'/system/tmp');
        $fs->remove($this->getTempDir().'/templates');
        $fs->remove($this->getTempDir().'/web/share');
        $fs->remove($this->getTempDir().'/web/system');
    }

    public function testCreatesTheContaoFolders(): void
    {
        $command = new InstallCommand($this->getTempDir(), 'files', $this->getTempDir().'/assets/images');
        $tester = new CommandTester($command);
        $code = $tester->execute([]);
        $output = $tester->getDisplay();

        $this->assertSame(0, $code);
        $this->assertStringContainsString(' * assets/css', $output);
        $this->assertStringContainsString(' * assets/images', $output);
        $this->assertStringContainsString(' * assets/js', $output);
        $this->assertStringContainsString(' * system/cache', $output);
        $this->assertStringContainsString(' * system/config', $output);
        $this->assertStringContainsString(' * system/tmp', $output);
        $this->assertStringContainsString(' * templates', $output);
        $this->assertStringContainsString(' * web/system', $output);
    }

    public function testHandlesCustomFilesAndImagesPaths(): void
    {
        $command = new InstallCommand($this->getTempDir(), 'files_test', $this->getTempDir().'/assets/images_test');
        $tester = new CommandTester($command);
        $code = $tester->execute([]);
        $display = $tester->getDisplay();

        $this->assertSame(0, $code);
        $this->assertStringContainsString(' * files_test', $display);
        $this->assertStringContainsString(' * assets/images_test', $display);
    }
}
