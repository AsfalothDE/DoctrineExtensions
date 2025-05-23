<?php

declare(strict_types=1);

/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gedmo\Tests\Sortable;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Gedmo\Sortable\SortableListener;
use Gedmo\Tests\Sortable\Fixture\Author;
use Gedmo\Tests\Sortable\Fixture\Category;
use Gedmo\Tests\Sortable\Fixture\Chapter;
use Gedmo\Tests\Sortable\Fixture\Customer;
use Gedmo\Tests\Sortable\Fixture\CustomerType;
use Gedmo\Tests\Sortable\Fixture\Event;
use Gedmo\Tests\Sortable\Fixture\Item;
use Gedmo\Tests\Sortable\Fixture\Node;
use Gedmo\Tests\Sortable\Fixture\NotifyNode;
use Gedmo\Tests\Sortable\Fixture\Paper;
use Gedmo\Tests\Sortable\Fixture\Series;
use Gedmo\Tests\Sortable\Fixture\SimpleListItem;
use Gedmo\Tests\Sortable\Fixture\Story;
use Gedmo\Tests\Tool\BaseTestCaseORM;

/**
 * These are tests for sortable behavior
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 */
final class SortableTest extends BaseTestCaseORM
{
    private ?int $nodeId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $evm = new EventManager();
        $evm->addEventSubscriber(new SortableListener());

        $this->getDefaultMockSqliteEntityManager($evm);
        $this->populate();
    }

    public function testShouldSetSortPositionToInsertedNode(): void
    {
        $node = $this->em->find(Node::class, $this->nodeId);
        static::assertSame(0, $node->getPosition());
    }

    public function testMoveLastPosition(): void
    {
        for ($i = 2; $i <= 10; ++$i) {
            $node = new Node();
            $node->setName('Node'.$i);
            $node->setPath('/');
            $this->em->persist($node);
        }
        $this->em->flush();

        $repo = $this->em->getRepository(Node::class);

        $node = $repo->findOneBy(['position' => 0]);
        $node->setPosition(-1);
        $this->em->flush();

        for ($i = 0; $i <= 8; ++$i) {
            $node = $repo->findOneBy(['position' => $i]);
            static::assertNotNull($node);
            static::assertSame('Node'.($i + 2), $node->getName());
        }

        $node = $repo->findOneBy(['position' => 9]);
        static::assertNotNull($node);
        static::assertSame('Node1', $node->getName());
    }

    public function testShouldSortManyNewNodes(): void
    {
        for ($i = 2; $i <= 10; ++$i) {
            $node = new Node();
            $node->setName('Node'.$i);
            $node->setPath('/');
            $this->em->persist($node);
        }
        $this->em->flush();

        $dql = 'SELECT node FROM '.Node::class.' node';
        $dql .= ' WHERE node.path = :path ORDER BY node.position';
        $nodes = $this->em
            ->createQuery($dql)
            ->setParameter('path', '/')
            ->getResult()
        ;

        static::assertCount(10, $nodes);
        static::assertSame('Node1', $nodes[0]->getName());
        static::assertSame(2, $nodes[2]->getPosition());
    }

    public function testShouldShiftPositionForward(): void
    {
        $node2 = new Node();
        $node2->setName('Node2');
        $node2->setPath('/');
        $this->em->persist($node2);

        $node = new Node();
        $node->setName('Node3');
        $node->setPath('/');
        $this->em->persist($node);

        $node = new Node();
        $node->setName('Node4');
        $node->setPath('/');
        $this->em->persist($node);

        $node = new Node();
        $node->setName('Node5');
        $node->setPath('/');
        $this->em->persist($node);

        $this->em->flush();

        static::assertSame(1, $node2->getPosition());
        $node2->setPosition(3);
        $this->em->persist($node2);
        $this->em->flush();

        $repo = $this->em->getRepository(Node::class);
        $nodes = $repo->getBySortableGroups(['path' => '/']);

        static::assertSame('Node1', $nodes[0]->getName());
        static::assertSame('Node3', $nodes[1]->getName());
        static::assertSame('Node4', $nodes[2]->getName());
        static::assertSame('Node2', $nodes[3]->getName());
        static::assertSame('Node5', $nodes[4]->getName());

        for ($i = 0; $i < count($nodes); ++$i) {
            static::assertSame($i, $nodes[$i]->getPosition());
        }
    }

    public function testShouldShiftPositionsProperlyWhenMoreThanOneWasUpdated(): void
    {
        $node2 = new Node();
        $node2->setName('Node2');
        $node2->setPath('/');
        $this->em->persist($node2);

        $node3 = new Node();
        $node3->setName('Node3');
        $node3->setPath('/');
        $this->em->persist($node3);

        $node = new Node();
        $node->setName('Node4');
        $node->setPath('/');
        $this->em->persist($node);

        $node = new Node();
        $node->setName('Node5');
        $node->setPath('/');
        $this->em->persist($node);

        $this->em->flush();

        static::assertSame(1, $node2->getPosition());
        $node2->setPosition(3);
        $node3->setPosition(4);
        $this->em->persist($node2);
        $this->em->persist($node3);
        $this->em->flush();

        $repo = $this->em->getRepository(Node::class);
        $nodes = $repo->getBySortableGroups(['path' => '/']);

        static::assertSame('Node1', $nodes[0]->getName());
        static::assertSame('Node4', $nodes[1]->getName());
        static::assertSame('Node5', $nodes[2]->getName());
        static::assertSame('Node2', $nodes[3]->getName());
        static::assertSame('Node3', $nodes[4]->getName());

        for ($i = 0; $i < count($nodes); ++$i) {
            static::assertSame($i, $nodes[$i]->getPosition());
        }
    }

    public function testShouldShiftPositionBackward(): void
    {
        $node = new Node();
        $node->setName('Node2');
        $node->setPath('/');
        $this->em->persist($node);

        $node = new Node();
        $node->setName('Node3');
        $node->setPath('/');
        $this->em->persist($node);

        $node2 = new Node();
        $node2->setName('Node4');
        $node2->setPath('/');
        $this->em->persist($node2);

        $node = new Node();
        $node->setName('Node5');
        $node->setPath('/');
        $this->em->persist($node);

        $this->em->flush();
        static::assertSame(3, $node2->getPosition());

        $node2->setPosition(1);
        $this->em->persist($node2);
        $this->em->flush();
        $this->em->clear(); // to reload from database

        $repo = $this->em->getRepository(Node::class);
        $nodes = $repo->getBySortableGroups(['path' => '/']);

        static::assertSame('Node1', $nodes[0]->getName());
        static::assertSame('Node4', $nodes[1]->getName());
        static::assertSame('Node2', $nodes[2]->getName());
        static::assertSame('Node3', $nodes[3]->getName());
        static::assertSame('Node5', $nodes[4]->getName());

        for ($i = 0; $i < count($nodes); ++$i) {
            static::assertSame($i, $nodes[$i]->getPosition());
        }
    }

    public function testShouldSyncPositionAfterDelete(): void
    {
        $repo = $this->em->getRepository(Node::class);

        $node2 = new Node();
        $node2->setName('Node2');
        $node2->setPath('/');
        $this->em->persist($node2);

        $node3 = new Node();
        $node3->setName('Node3');
        $node3->setPath('/');
        $this->em->persist($node3);

        $this->em->flush();

        $node1 = $repo->findOneBy(['name' => 'Node1']);
        $this->em->remove($node2);
        $this->em->flush();

        // test if synced on objects in memory correctly
        static::assertSame(0, $node1->getPosition());
        static::assertSame(1, $node3->getPosition());

        // test if persisted correctly
        $this->em->clear();
        $nodes = $repo->findAll();
        static::assertCount(2, $nodes);
        static::assertSame(0, $nodes[0]->getPosition());
        static::assertSame(1, $nodes[1]->getPosition());
    }

    /**
     * Test if the sorting is correct if multiple items are deleted.
     *
     * Example:
     *     Position | Element | Action | Expected Position
     *            0 | Node1   |        |                 0
     *            1 | Node2   | delete |
     *            2 | Node3   | delete |
     *            3 | Node4   |        |                 1
     */
    public function testShouldSyncPositionAfterMultipleDeletes(): void
    {
        $repo = $this->em->getRepository(Node::class);

        $node2 = new Node();
        $node2->setName('Node2');
        $node2->setPath('/');
        $this->em->persist($node2);

        $node3 = new Node();
        $node3->setName('Node3');
        $node3->setPath('/');
        $this->em->persist($node3);

        $node4 = new Node();
        $node4->setName('Node4');
        $node4->setPath('/');
        $this->em->persist($node4);

        $this->em->flush();

        $node1 = $repo->findOneBy(['name' => 'Node1']);
        $this->em->remove($node2);
        $this->em->remove($node3);
        $this->em->flush();

        // test if synced on objects in memory correctly
        static::assertSame(0, $node1->getPosition());
        static::assertSame(1, $node4->getPosition());

        // test if persisted correctly
        $this->em->clear();
        $nodes = $repo->findAll();
        static::assertCount(2, $nodes);
        static::assertSame(0, $nodes[0]->getPosition());
        static::assertSame(1, $nodes[1]->getPosition());
    }

    /**
     * Test if the sorting is correct if multiple items are added and deleted.
     *
     * Example:
     *     Position | Element | Action | Expected Position
     *            0 | Node1   |        |                 0
     *            1 | Node2   | delete |
     *            2 | Node3   | delete |
     *            3 | Node4   |        |                 1
     *              | Node5   | add    |                 2
     *              | Node6   | add    |                 3
     */
    public function testShouldSyncPositionAfterMultipleAddsAndMultipleDeletes(): void
    {
        $repo = $this->em->getRepository(Node::class);

        $node2 = new Node();
        $node2->setName('Node2');
        $node2->setPath('/');
        $this->em->persist($node2);

        $node3 = new Node();
        $node3->setName('Node3');
        $node3->setPath('/');
        $this->em->persist($node3);

        $node4 = new Node();
        $node4->setName('Node4');
        $node4->setPath('/');
        $this->em->persist($node4);

        $this->em->flush();

        $node1 = $repo->findOneBy(['name' => 'Node1']);

        $this->em->remove($node2);

        $node5 = new Node();
        $node5->setName('Node5');
        $node5->setPath('/');
        $this->em->persist($node5);

        $node6 = new Node();
        $node6->setName('Node6');
        $node6->setPath('/');
        $this->em->persist($node6);

        $this->em->remove($node3);

        $this->em->flush();

        // test if synced on objects in memory correctly
        static::assertSame(0, $node1->getPosition());
        static::assertSame(1, $node4->getPosition());
        static::assertSame(2, $node5->getPosition());
        static::assertSame(3, $node6->getPosition());

        // test if persisted correctly
        $this->em->clear();
        $nodes = $repo->findAll();
        static::assertCount(4, $nodes);
        static::assertSame(0, $nodes[0]->getPosition());
        static::assertSame('Node1', $nodes[0]->getName());
        static::assertSame(1, $nodes[1]->getPosition());
        static::assertSame('Node4', $nodes[1]->getName());
        static::assertSame(2, $nodes[2]->getPosition());
        static::assertSame('Node5', $nodes[2]->getName());
        static::assertSame(3, $nodes[3]->getPosition());
        static::assertSame('Node6', $nodes[3]->getName());
    }

    /**
     * This is a test case for issue #1209
     */
    public function testShouldRollbackPositionAfterExceptionOnDelete(): void
    {
        $repo = $this->em->getRepository(CustomerType::class);

        $customerType1 = new CustomerType();
        $customerType1->setName('CustomerType1');
        $this->em->persist($customerType1);

        $customerType2 = new CustomerType();
        $customerType2->setName('CustomerType2');
        $this->em->persist($customerType2);

        $customerType3 = new CustomerType();
        $customerType3->setName('CustomerType3');
        $this->em->persist($customerType3);

        $customer = new Customer();
        $customer->setName('Customer');
        $customer->setType($customerType2);
        $this->em->persist($customer);

        $this->em->flush();

        try {
            // now delete the second customer type, which should fail
            // because of the foreign key reference
            $this->em->remove($customerType2);
            $this->em->flush();

            static::fail('Foreign key constraint violation exception not thrown.');
        } catch (ForeignKeyConstraintViolationException $e) {
            $customerTypes = $repo->findAll();

            static::assertCount(3, $customerTypes);

            static::assertSame(0, $customerTypes[0]->getPosition(), 'The sorting position has not been rolled back.');
            static::assertSame(1, $customerTypes[1]->getPosition(), 'The sorting position has not been rolled back.');
            static::assertSame(2, $customerTypes[2]->getPosition(), 'The sorting position has not been rolled back.');
        }
    }

    public function testShouldGroupByAssociation(): void
    {
        $category1 = new Category();
        $category1->setName('Category1');
        $this->em->persist($category1);
        $category2 = new Category();
        $category2->setName('Category2');
        $this->em->persist($category2);
        $this->em->flush();

        $item3 = new Item();
        $item3->setName('Item3');
        $item3->setCategory($category1);
        $this->em->persist($item3);

        $item4 = new Item();
        $item4->setName('Item4');
        $item4->setCategory($category1);
        $this->em->persist($item4);

        $this->em->flush();

        $item1 = new Item();
        $item1->setName('Item1');
        $item1->setPosition(0);
        $item1->setCategory($category1);
        $this->em->persist($item1);

        $item2 = new Item();
        $item2->setName('Item2');
        $item2->setPosition(0);
        $item2->setCategory($category1);
        $this->em->persist($item2);

        $item2 = new Item();
        $item2->setName('Item2_2');
        $item2->setPosition(0);
        $item2->setCategory($category2);
        $this->em->persist($item2);
        $this->em->flush();

        $item1 = new Item();
        $item1->setName('Item1_2');
        $item1->setPosition(0);
        $item1->setCategory($category2);
        $this->em->persist($item1);
        $this->em->flush();

        $repo = $this->em->getRepository(Category::class);
        $category1 = $repo->findOneBy(['name' => 'Category1']);
        $category2 = $repo->findOneBy(['name' => 'Category2']);

        $repo = $this->em->getRepository(Item::class);

        $items = $repo->getBySortableGroups(['category' => $category1]);

        static::assertSame('Item1', $items[0]->getName());
        static::assertSame('Category1', $items[0]->getCategory()->getName());

        static::assertSame('Item2', $items[1]->getName());
        static::assertSame('Category1', $items[1]->getCategory()->getName());

        static::assertSame('Item3', $items[2]->getName());
        static::assertSame('Category1', $items[2]->getCategory()->getName());

        static::assertSame('Item4', $items[3]->getName());
        static::assertSame('Category1', $items[3]->getCategory()->getName());

        $items = $repo->getBySortableGroups(['category' => $category2]);

        static::assertSame('Item1_2', $items[0]->getName());
        static::assertSame('Category2', $items[0]->getCategory()->getName());

        static::assertSame('Item2_2', $items[1]->getName());
        static::assertSame('Category2', $items[1]->getCategory()->getName());
    }

    public function testShouldGroupByNewAssociation(): void
    {
        $category1 = new Category();
        $category1->setName('Category1');

        $item1 = new Item();
        $item1->setName('Item1');
        $item1->setPosition(0);
        $item1->setCategory($category1);
        $this->em->persist($item1);
        $this->em->persist($category1);
        $this->em->flush();

        $repo = $this->em->getRepository(Category::class);
        $category1 = $repo->findOneBy(['name' => 'Category1']);

        $repo = $this->em->getRepository(Item::class);

        $items = $repo->getBySortableGroups(['category' => $category1]);

        static::assertSame('Item1', $items[0]->getName());
        static::assertSame('Category1', $items[0]->getCategory()->getName());
    }

    public function testShouldGroupByDateTimeValue(): void
    {
        $event1 = new Event();
        $event1->setDateTime(new \DateTime('2012-09-15 00:00:00'));
        $event1->setName('Event1');
        $this->em->persist($event1);
        $event2 = new Event();
        $event2->setDateTime(new \DateTime('2012-09-15 00:00:00'));
        $event2->setName('Event2');
        $this->em->persist($event2);
        $event3 = new Event();
        $event3->setDateTime(new \DateTime('2012-09-16 00:00:00'));
        $event3->setName('Event3');
        $this->em->persist($event3);

        $this->em->flush();

        $event4 = new Event();
        $event4->setDateTime(new \DateTime('2012-09-15 00:00:00'));
        $event4->setName('Event4');
        $this->em->persist($event4);

        $event5 = new Event();
        $event5->setDateTime(new \DateTime('2012-09-16 00:00:00'));
        $event5->setName('Event5');
        $this->em->persist($event5);

        $this->em->flush();

        static::assertSame(0, $event1->getPosition());
        static::assertSame(1, $event2->getPosition());
        static::assertSame(0, $event3->getPosition());
        static::assertSame(2, $event4->getPosition());
        static::assertSame(1, $event5->getPosition());
    }

    public function testShouldFixIssue226(): void
    {
        $paper1 = new Paper();
        $paper1->setName('Paper1');
        $this->em->persist($paper1);

        $paper2 = new Paper();
        $paper2->setName('Paper2');
        $this->em->persist($paper2);

        $author1 = new Author();
        $author1->setName('Author1');
        $author1->setPaper($paper1);

        $author2 = new Author();
        $author2->setName('Author2');
        $author2->setPaper($paper1);

        $author3 = new Author();
        $author3->setName('Author3');
        $author3->setPaper($paper2);

        $this->em->persist($author1);
        $this->em->persist($author2);
        $this->em->persist($author3);
        $this->em->flush();

        static::assertSame(0, $author1->getPosition());
        static::assertSame(1, $author2->getPosition());
        static::assertSame(0, $author3->getPosition());

        // update position
        $author3->setPaper($paper1);
        $author3->setPosition(0); // same as before, no changes
        $this->em->persist($author3);
        $this->em->flush();

        static::assertSame(1, $author1->getPosition());
        static::assertSame(2, $author2->getPosition());
        static::assertSame(0, $author3->getPosition());

        // this is failing for whatever reasons
        $author3->setPosition(0);
        $this->em->persist($author3);
        $this->em->flush();

        $this->em->clear(); // @TODO: this should not be required

        $author1 = $this->em->find(Author::class, $author1->getId());
        $author2 = $this->em->find(Author::class, $author2->getId());
        $author3 = $this->em->find(Author::class, $author3->getId());

        static::assertSame(1, $author1->getPosition());
        static::assertSame(2, $author2->getPosition());
        static::assertSame(0, $author3->getPosition());
    }

    public function testShouldFixIssue1445(): void
    {
        $paper1 = new Paper();
        $paper1->setName('Paper1');
        $this->em->persist($paper1);

        $paper2 = new Paper();
        $paper2->setName('Paper2');
        $this->em->persist($paper2);

        $author1 = new Author();
        $author1->setName('Author1');
        $author1->setPaper($paper1);

        $author2 = new Author();
        $author2->setName('Author2');
        $author2->setPaper($paper1);

        $this->em->persist($author1);
        $this->em->persist($author2);
        $this->em->flush();

        static::assertSame(0, $author1->getPosition());
        static::assertSame(1, $author2->getPosition());

        // update position
        $author2->setPaper($paper2);
        $author2->setPosition(0); // Position has changed author2 was at position 1 in paper1 and now 0 in paper2, so it can be in changeSets
        $this->em->persist($author2);
        $this->em->flush();

        static::assertSame(0, $author1->getPosition());
        static::assertSame(0, $author2->getPosition());

        $this->em->clear(); // @TODO: this should not be required

        $repo = $this->em->getRepository(Author::class);
        $author1 = $repo->findOneBy(['id' => $author1->getId()]);
        $author2 = $repo->findOneBy(['id' => $author2->getId()]);

        static::assertSame(0, $author1->getPosition());
        static::assertSame(0, $author2->getPosition());
    }

    public function testShouldFixIssue1462(): void
    {
        $paper1 = new Paper();
        $paper1->setName('Paper1');
        $this->em->persist($paper1);

        $paper2 = new Paper();
        $paper2->setName('Paper2');
        $this->em->persist($paper2);

        $author1 = new Author();
        $author1->setName('Author1');
        $author1->setPaper($paper1);

        $author2 = new Author();
        $author2->setName('Author2');
        $author2->setPaper($paper1);

        $author3 = new Author();
        $author3->setName('Author3');
        $author3->setPaper($paper2);

        $author4 = new Author();
        $author4->setName('Author4');
        $author4->setPaper($paper2);

        $author5 = new Author();
        $author5->setName('Author5');
        $author5->setPaper($paper1);

        $this->em->persist($author1);
        $this->em->persist($author2);
        $this->em->persist($author3);
        $this->em->persist($author4);
        $this->em->persist($author5);
        $this->em->flush();

        static::assertSame(0, $author1->getPosition());
        static::assertSame(1, $author2->getPosition());
        static::assertSame(2, $author5->getPosition());

        static::assertSame(0, $author3->getPosition());
        static::assertSame(1, $author4->getPosition());

        // update paper: the position is still 1.
        $author4->setPaper($paper1);
        $this->em->persist($author4);
        $this->em->flush();

        static::assertSame(0, $author1->getPosition());
        static::assertSame(1, $author4->getPosition());
        static::assertSame(2, $author2->getPosition());
        static::assertSame(3, $author5->getPosition());

        static::assertSame(0, $author3->getPosition());

        $this->em->clear(); // @TODO: this should not be required

        $repo = $this->em->getRepository(Author::class);
        $author1 = $repo->findOneBy(['id' => $author1->getId()]);
        $author2 = $repo->findOneBy(['id' => $author2->getId()]);
        $author3 = $repo->findOneBy(['id' => $author3->getId()]);
        $author4 = $repo->findOneBy(['id' => $author4->getId()]);
        $author5 = $repo->findOneBy(['id' => $author5->getId()]);

        static::assertSame(0, $author1->getPosition());
        static::assertSame(1, $author4->getPosition());
        static::assertSame(2, $author2->getPosition());
        static::assertSame(3, $author5->getPosition());

        static::assertSame(0, $author3->getPosition());
    }

    public function testPositionShouldBeTheSameAfterFlush(): void
    {
        $nodes = [];
        for ($i = 2; $i <= 10; ++$i) {
            $node = new Node();
            $node->setName('Node'.$i);
            $node->setPath('/');
            $this->em->persist($node);
            $nodes[] = $node;
        }
        $this->em->flush();

        $node1 = $this->em->find(Node::class, $this->nodeId);
        $node1->setPosition(5);

        $this->em->flush();

        static::assertSame(5, $node1->getPosition());

        $this->em->detach($node1);
        $node1 = $this->em->find(Node::class, $this->nodeId);
        static::assertSame(5, $node1->getPosition());
    }

    public function testIncrementPositionOfLastObjectByOne(): void
    {
        $node0 = $this->em->find(Node::class, $this->nodeId);

        $nodes = [$node0];

        for ($i = 2; $i <= 5; ++$i) {
            $node = new Node();
            $node->setName('Node'.$i);
            $node->setPath('/');
            $this->em->persist($node);
            $nodes[] = $node;
        }
        $this->em->flush();

        static::assertSame(4, $nodes[4]->getPosition());

        $node4NewPosition = $nodes[4]->getPosition();
        ++$node4NewPosition;

        $nodes[4]->setPosition($node4NewPosition);

        $this->em->persist($nodes[4]);
        $this->em->flush();

        static::assertSame(4, $nodes[4]->getPosition());
    }

    public function testSetOutOfBoundsHighPosition(): void
    {
        $node0 = $this->em->find(Node::class, $this->nodeId);

        $nodes = [$node0];

        for ($i = 2; $i <= 5; ++$i) {
            $node = new Node();
            $node->setName('Node'.$i);
            $node->setPath('/');
            $this->em->persist($node);
            $nodes[] = $node;
        }
        $this->em->flush();

        static::assertSame(4, $nodes[4]->getPosition());

        $nodes[4]->setPosition(100);

        $this->em->persist($nodes[4]);
        $this->em->flush();

        static::assertSame(4, $nodes[4]->getPosition());
    }

    public function testShouldFixIssue1809(): void
    {
        if (!class_exists(AnnotationDriver::class)) {
            static::markTestSkipped('Test uses a fixture using the deprecated "NOTIFY" change tracking policy.');
        }

        $manager = $this->em;
        $nodes = [];
        for ($i = 1; $i <= 3; ++$i) {
            $node = new NotifyNode();
            $node->setName('Node'.$i);
            $node->setPath('/');
            $manager->persist($node);
            $nodes[] = $node;
            $manager->flush();
        }
        foreach ($nodes as $i => $node) {
            $position = $node->getPosition();
            static::assertSame($i, $position);
        }
    }

    public function testShouldRespectACustomStartWithConfig(): void
    {
        $story = new Story();
        $story->setTitle('Story 1');

        $chapters = [];

        for ($i = 1; $i <= 5; ++$i) {
            $chapters[$i] = new Chapter();
            $chapters[$i]->setTitle('Chapter '.$i);
            $story->addChapter($chapters[$i]);
        }

        $this->em->persist($story);

        $this->em->flush();
        $this->em->clear(); // @TODO: this should not be required

        static::assertSame(1, $chapters[1]->getNumber());
        static::assertSame(2, $chapters[2]->getNumber());
        static::assertSame(3, $chapters[3]->getNumber());
        static::assertSame(4, $chapters[4]->getNumber());
        static::assertSame(5, $chapters[5]->getNumber());
    }

    public function testShouldRespectACustomStartWithConfigOnUpdate(): void
    {
        $story = new Story();
        $story->setTitle('Story 1');

        $node1 = new Chapter();
        $node1->setTitle('Node1');
        $node1->setStory($story);
        $this->em->persist($node1);

        $node2 = new Chapter();
        $node2->setTitle('Node2');
        $node2->setStory($story);
        $this->em->persist($node2);

        $node3 = new Chapter();
        $node3->setTitle('Node3');
        $node3->setStory($story);
        $this->em->persist($node3);

        $node4 = new Chapter();
        $node4->setTitle('Node4');
        $node4->setStory($story);
        $this->em->persist($node4);

        $node5 = new Chapter();
        $node5->setTitle('Node5');
        $node5->setStory($story);
        $this->em->persist($node5);

        $this->em->flush();

        static::assertSame(1, $node1->getNumber());
        $node2->setNumber(4);
        $node3->setNumber(5);
        $this->em->persist($node2);
        $this->em->persist($node3);
        $this->em->flush();

        $repo = $this->em->getRepository(Chapter::class);
        $nodes = $repo->getBySortableGroups(['story' => $story]);

        static::assertSame('Node1', $nodes[0]->getTitle());
        static::assertSame(1, $nodes[0]->getNumber());
        static::assertSame('Node4', $nodes[1]->getTitle());
        static::assertSame(2, $nodes[1]->getNumber());
        static::assertSame('Node5', $nodes[2]->getTitle());
        static::assertSame(3, $nodes[2]->getNumber());
        static::assertSame('Node2', $nodes[3]->getTitle());
        static::assertSame(4, $nodes[3]->getNumber());
        static::assertSame('Node3', $nodes[4]->getTitle());
        static::assertSame(5, $nodes[4]->getNumber());

        for ($i = 0; $i < count($nodes); ++$i) {
            static::assertSame($i + 1, $nodes[$i]->getNumber());
        }

        $this->em->remove($node5);
        $this->em->flush();

        $repo = $this->em->getRepository(Chapter::class);
        $nodes = $repo->getBySortableGroups(['story' => $story]);

        static::assertSame('Node1', $nodes[0]->getTitle());
        static::assertSame(1, $nodes[0]->getNumber());
        static::assertSame('Node4', $nodes[1]->getTitle());
        static::assertSame(2, $nodes[1]->getNumber());
        static::assertSame('Node2', $nodes[2]->getTitle());
        static::assertSame(3, $nodes[2]->getNumber());
        static::assertSame('Node3', $nodes[3]->getTitle());
        static::assertSame(4, $nodes[3]->getNumber());

        for ($i = 0; $i < count($nodes); ++$i) {
            static::assertSame($i + 1, $nodes[$i]->getNumber());
        }

        $this->em->remove($node1);
        $this->em->flush();

        $repo = $this->em->getRepository(Chapter::class);
        $nodes = $repo->getBySortableGroups(['story' => $story]);

        static::assertSame('Node4', $nodes[0]->getTitle());
        static::assertSame(1, $nodes[0]->getNumber());
        static::assertSame('Node2', $nodes[1]->getTitle());
        static::assertSame(2, $nodes[1]->getNumber());
        static::assertSame('Node3', $nodes[2]->getTitle());
        static::assertSame(3, $nodes[2]->getNumber());

        for ($i = 0; $i < count($nodes); ++$i) {
            static::assertSame($i + 1, $nodes[$i]->getNumber());
        }
    }

    public function testShouldSetPositionToNullWhenNullableIsFalse(): void
    {
        $series1 = new Series();
        $series1->setName('Series 1');
        $this->em->persist($series1);

        $series2 = new Series();
        $series2->setName('Series 2');
        $this->em->persist($series2);

        $volume1 = new Story();
        $volume1->setTitle('Volume 1');
        $volume1->setSeries($series1);
        $this->em->persist($volume1);

        $volume2 = new Story();
        $volume2->setTitle('Volume 2');
        $volume2->setSeries($series1);
        $this->em->persist($volume2);

        $story1 = new Story();
        $story1->setTitle('Independant story 1');
        $this->em->persist($story1);

        $story2 = new Story();
        $story2->setTitle('Independant story 2');
        $this->em->persist($story2);

        $this->em->flush();

        static::assertSame(1, $volume1->getVolume());
        static::assertSame($series1, $volume1->getSeries());
        static::assertSame(2, $volume2->getVolume());
        static::assertSame($series1, $volume2->getSeries());
        static::assertNull($story1->getSeries());
        static::assertNull($story1->getVolume());
        static::assertNull($story2->getSeries());
        static::assertNull($story2->getVolume());

        $story1->setSeries($series2);
        $story2->setSeries($series2);
        $this->em->flush();

        $repo = $this->em->getRepository(Story::class);
        $nodes = $repo->getBySortableGroups(['series' => $series2]);

        static::assertCount(2, $nodes);
        static::assertSame(1, $nodes[0]->getVolume());
        static::assertSame('Independant story 1', $nodes[0]->getTitle());
        static::assertSame(2, $nodes[1]->getVolume());
        static::assertSame('Independant story 2', $nodes[1]->getTitle());
    }

    protected function getUsedEntityFixtures(): array
    {
        $fixtures = [
            Node::class,
            Item::class,
            Category::class,
            SimpleListItem::class,
            Author::class,
            Paper::class,
            Event::class,
            Customer::class,
            CustomerType::class,
            Series::class,
            Story::class,
            Chapter::class,
        ];

        if (class_exists(AnnotationDriver::class)) {
            $fixtures[] = NotifyNode::class;
        }

        return $fixtures;
    }

    private function populate(): void
    {
        $node = new Node();
        $node->setName('Node1');
        $node->setPath('/');

        $this->em->persist($node);
        $this->em->flush();
        $this->nodeId = $node->getId();
    }
}
