<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/events2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Events2\Tests\Functional\Domain\Factory;

use JWeiland\Events2\Configuration\ExtConf;
use JWeiland\Events2\Domain\Factory\DayFactory;
use JWeiland\Events2\Domain\Model\Day;
use JWeiland\Events2\Domain\Model\Event;
use JWeiland\Events2\Domain\Model\Location;
use JWeiland\Events2\Domain\Model\Organizer;
use JWeiland\Events2\Domain\Model\Time;
use JWeiland\Events2\Domain\Repository\EventRepository;
use JWeiland\Events2\Service\DatabaseService;
use JWeiland\Events2\Service\DayRelationService;
use JWeiland\Events2\Utility\DateTimeUtility;
use Nimut\TestingFramework\TestCase\FunctionalTestCase;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\Query;
use TYPO3\CMS\Extbase\Persistence\Generic\QueryFactory;
use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * Functional test for DayFactory
 */
class DayFactoryTest extends FunctionalTestCase
{
    /**
     * @var DayFactory
     */
    protected $subject;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @var QuerySettingsInterface
     */
    protected $querySettings;

    /**
     * @var EventRepository
     */
    protected $eventRepository;

    /**
     * @var array
     */
    protected $testExtensionsToLoad = [
        'typo3conf/ext/events2',
        'typo3conf/ext/maps2'
    ];

    public function setUp(): void
    {
        parent::setUp();

        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $persistenceManager = $this->objectManager->get(PersistenceManagerInterface::class);
        $this->querySettings = $this->objectManager->get(QuerySettingsInterface::class);
        $this->querySettings->setStoragePageIds([11, 40]);
        $dayRelationService = $this->objectManager->get(DayRelationService::class);
        $this->eventRepository = $this->objectManager->get(EventRepository::class);
        $this->eventRepository->setDefaultQuerySettings($this->querySettings);

        $this->subject = new DayFactory(
            new DatabaseService(new ExtConf(new ExtensionConfiguration()), new DateTimeUtility()),
            $this->eventRepository,
            $dayRelationService
        );

        // As we need day related records, we can not use XML import functionality
        $organizer = new Organizer();
        $organizer->setPid(11);
        $organizer->setOrganizer('Stefan');

        $location = new Location();
        $location->setPid(11);
        $location->setLocation('Market');

        $eventBegin = new \DateTime('midnight');

        $eventTime = new Time();
        $eventTime->setPid(11);
        $eventTime->setTimeBegin('07:30');

        $event = new Event();
        $event->setPid(11);
        $event->setEventType('single');
        $event->setTopOfList(false);
        $event->setTitle('Exactly match');
        $event->setEventBegin($eventBegin);
        $event->setEventTime($eventTime);
        $event->setXth(0);
        $event->setWeekday(0);
        $event->setEachWeeks(0);
        $event->setEachMonths(0);
        $event->setRecurringEnd(null);
        $event->setFreeEntry(false);
        $event->addOrganizer($organizer);
        $event->setLocation($location);
        $persistenceManager->add($event);

        $eventBegin = new \DateTime('tomorrow midnight');

        $event = new Event();
        $event->setPid(11);
        $event->setEventType('single');
        $event->setTopOfList(false);
        $event->setTitle('Next match');
        $event->setEventBegin($eventBegin);
        $event->setEventTime($eventTime);
        $event->setXth(0);
        $event->setWeekday(0);
        $event->setEachWeeks(0);
        $event->setEachMonths(0);
        $event->setRecurringEnd(null);
        $event->setFreeEntry(false);
        $event->addOrganizer($organizer);
        $event->setLocation($location);
        $persistenceManager->add($event);

        $eventBegin = new \DateTime('yesterday midnight');

        $event = new Event();
        $event->setPid(11);
        $event->setEventType('single');
        $event->setTopOfList(false);
        $event->setTitle('Previous match');
        $event->setEventBegin($eventBegin);
        $event->setEventTime($eventTime);
        $event->setXth(0);
        $event->setWeekday(0);
        $event->setEachWeeks(0);
        $event->setEachMonths(0);
        $event->setRecurringEnd(null);
        $event->setFreeEntry(false);
        $event->addOrganizer($organizer);
        $event->setLocation($location);
        $persistenceManager->add($event);

        // This date is out of time frame.
        // As day records for event type "single" will always be generated we have to switch to another type.
        // So no day records should be created for that event
        $eventBegin = new \DateTime('-1 year');
        $recurringEnd = clone $eventBegin;
        $recurringEnd->modify('+2 weeks');

        $event = new Event();
        $event->setPid(11);
        $event->setEventType('recurring');
        $event->setTopOfList(false);
        $event->setTitle('Previous match');
        $event->setEventBegin($eventBegin);
        $event->setEventTime($eventTime);
        $event->setXth(0);
        $event->setWeekday(0);
        $event->setEachWeeks(1);
        $event->setEachMonths(0);
        $event->setRecurringEnd($recurringEnd);
        $event->setFreeEntry(false);
        $event->addOrganizer($organizer);
        $event->setLocation($location);
        $persistenceManager->add($event);

        $persistenceManager->persistAll();

        $events = $this->eventRepository->findAll();
        foreach ($events as $event) {
            $dayRelationService->createDayRelations($event->getUid());
        }
    }

    public function tearDown(): void
    {
        unset($this->dayRepository);
        parent::tearDown();
    }

    /**
     * @test
     */
    public function findDayWithDateTimeOfTodayWillFindExactlyMatchingDay(): void
    {
        $date = new \DateTime('midnight');
        $date->modify('07:30:00');
        $queryFactory = $this->objectManager->get(QueryFactory::class);

        /** @var Query $query */
        $query = $queryFactory->create(Day::class);
        $query->setQuerySettings($this->querySettings);

        $event = $this->eventRepository->findByUid(1);

        // Try to get exactly matching day record for today
        $day = $this->subject->findDayByEventAndTimestamp($event, (int)$date->format('U'), $query);
        self::assertSame(
            1,
            $day->getEvent()->getUid()
        );
        self::assertSame(
            2,
            $day->getUid()
        );
    }

    /**
     * @test
     */
    public function findDayWithDateTimeOfYesterdayWillFindNextMatchingDay(): void
    {
        $date = new \DateTime('yesterday midnight');
        $date->modify('07:30:00');
        $queryFactory = $this->objectManager->get(QueryFactory::class);

        /** @var Query $query */
        $query = $queryFactory->create(Day::class);
        $query->setQuerySettings($this->querySettings);

        $event = $this->eventRepository->findByUid(1);

        // Try to get a day record for yesterday, where no day for this event exists.
        $day = $this->subject->findDayByEventAndTimestamp($event, (int)$date->format('U'), $query);
        self::assertSame(
            1,
            $day->getEvent()->getUid()
        );
        self::assertSame(
            2,
            $day->getUid()
        );
    }

    /**
     * @test
     */
    public function findDayWithDateTimeOfTomorrowWillFindPreviousMatchingDay(): void
    {
        $date = new \DateTime('tomorrow midnight');
        $date->modify('07:30:00');
        $queryFactory = $this->objectManager->get(QueryFactory::class);

        /** @var Query $query */
        $query = $queryFactory->create(Day::class);
        $query->setQuerySettings($this->querySettings);

        $event = $this->eventRepository->findByUid(1);

        // Try to get a day record for tomorrow, where no day for this event exists.
        $day = $this->subject->findDayByEventAndTimestamp($event, (int)$date->format('U'), $query);
        self::assertSame(
            1,
            $day->getEvent()->getUid()
        );
        self::assertSame(
            2,
            $day->getUid()
        );
    }

    /**
     * @test
     */
    public function findDayWillFindNextMatchingDay(): void
    {
        $date = new \DateTime('midnight');
        $date->modify('07:30:00');
        $queryFactory = $this->objectManager->get(QueryFactory::class);

        /** @var Query $query */
        $query = $queryFactory->create(Day::class);
        $query->setQuerySettings($this->querySettings);

        $event = $this->eventRepository->findByUid(2);

        // This Timestamp isn't in DB for event 2
        $day = $this->subject->findDayByEventAndTimestamp($event, (int)$date->format('U'), $query);
        self::assertSame(
            2,
            $day->getEvent()->getUid()
        );
        self::assertSame(
            3,
            $day->getUid()
        );
    }

    /**
     * @test
     */
    public function findDayWillFindPreviousNextMatchingDay(): void
    {
        $date = new \DateTime('midnight');
        $date->modify('07:30:00');
        $queryFactory = $this->objectManager->get(QueryFactory::class);

        /** @var Query $query */
        $query = $queryFactory->create(Day::class);
        $query->setQuerySettings($this->querySettings);

        $event = $this->eventRepository->findByUid(3);

        // This Timestamp isn't in DB for event 3
        $day = $this->subject->findDayByEventAndTimestamp($event, (int)$date->format('U'), $query);
        self::assertSame(
            3,
            $day->getEvent()->getUid()
        );
        self::assertSame(
            1,
            $day->getUid()
        );
    }

    /**
     * @test
     */
    public function findDayWillBuildDayItselfButKeepsTime(): void
    {
        $date = new \DateTime('midnight');
        $date->modify('07:30:00');
        $queryFactory = $this->objectManager->get(QueryFactory::class);

        /** @var Query $query */
        $query = $queryFactory->create(Day::class);
        $query->setQuerySettings($this->querySettings);

        $event = $this->eventRepository->findByUid(4);

        $day = $this->subject->findDayByEventAndTimestamp($event, (int)$date->format('U'), $query);
        self::assertSame(
            4,
            $day->getEvent()->getUid()
        );
        self::assertNull($day->getUid());

        $expectedDate = new \DateTime('midnight');
        $expectedDate->modify('-1 year')->modify('07:30:00');

        self::assertEquals(
            $expectedDate,
            $day->getDayTime()
        );
    }
}
