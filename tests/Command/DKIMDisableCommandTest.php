<?php

declare(strict_types=1);
/**
 * This file is part of the mailserver-admin package.
 * (c) Jeffrey Boehm <https://github.com/jeboehm/mailserver-admin>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Command;

use App\Command\DKIMDisableCommand;
use App\Entity\Domain;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class DKIMDisableCommandTest extends TestCase
{
    /** @var CommandTester */
    private $commandTester;

    /** @var EntityManagerInterface|MockObject */
    private $managerMock;

    protected function setUp(): void
    {
        $this->managerMock = $this->createMock(EntityManagerInterface::class);

        $application = new Application();
        $application->add(new DKIMDisableCommand(null, $this->managerMock));

        $this->commandTester = new CommandTester($application->find('dkim:disable'));
    }

    public function testDomainNotFound(): void
    {
        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $this->managerMock
            ->method('getRepository')
            ->with(Domain::class)
            ->willReturn($repository);

        $this->managerMock->expects($this->never())->method('persist');

        $this->commandTester->execute(['domain' => 'example.com']);

        $this->assertEquals(
            'Domain "example.com" was not found.',
            trim($this->commandTester->getDisplay(true))
        );
        $this->assertEquals(1, $this->commandTester->getStatusCode());
    }

    public function testAbort(): void
    {
        $repository = $this->createMock(ObjectRepository::class);

        $domain = new Domain();
        $domain->setName('example.com');
        $domain->setDkimPrivateKey('xy');
        $domain->setDkimSelector('xy');
        $domain->setDkimEnabled(true);

        $repository->method('findOneBy')->willReturn($domain);

        $this->managerMock
            ->method('getRepository')
            ->with(Domain::class)
            ->willReturn($repository);

        $this->managerMock->expects($this->never())->method('flush');

        $this->commandTester->setInputs(['no']);
        $this->commandTester->execute(['domain' => 'example.com']);

        $this->assertEquals(
            'Do you want to disable DKIM for domain "example.com"?Aborting.',
            trim($this->commandTester->getDisplay(true))
        );
        $this->assertEquals(1, $this->commandTester->getStatusCode());
    }

    public function testExecute(): void
    {
        $repository = $this->createMock(ObjectRepository::class);

        $domain = new Domain();
        $domain->setDkimPrivateKey('xy');
        $domain->setDkimSelector('xy');
        $domain->setDkimEnabled(true);

        $repository->method('findOneBy')->willReturn($domain);

        $this->managerMock
            ->method('getRepository')
            ->with(Domain::class)
            ->willReturn($repository);

        $this->managerMock->expects($this->once())->method('flush');

        $this->commandTester->setInputs(['Y']);
        $this->commandTester->execute(['domain' => 'example.com']);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
        $this->assertEmpty($domain->getDkimPrivateKey());
        $this->assertEmpty($domain->getDkimSelector());
        $this->assertFalse($domain->getDkimEnabled());
    }
}
