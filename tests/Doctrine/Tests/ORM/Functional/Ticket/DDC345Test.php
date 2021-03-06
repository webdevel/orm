<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Tests\OrmFunctionalTestCase;

class DDC345Test extends OrmFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        //$this->_em->getConnection()->getConfiguration()->setSQLLogger(new \Doctrine\DBAL\Logging\EchoSQLLogger);
        $this->_schemaTool->createSchema(
            [
                $this->_em->getClassMetadata(DDC345User::class),
                $this->_em->getClassMetadata(DDC345Group::class),
                $this->_em->getClassMetadata(DDC345Membership::class),
            ]
        );
    }

    public function testTwoIterateHydrations(): void
    {
        // Create User
        $user       = new DDC345User();
        $user->name = 'Test User';
        $this->_em->persist($user); // $em->flush() does not change much here

        // Create Group
        $group       = new DDC345Group();
        $group->name = 'Test Group';
        $this->_em->persist($group); // $em->flush() does not change much here

        $membership        = new DDC345Membership();
        $membership->group = $group;
        $membership->user  = $user;
        $membership->state = 'active';

        //$this->_em->persist($membership); // COMMENT OUT TO SEE BUG
        /*
        This should be not necessary, but without, its PrePersist is called twice,
        $membership seems to be persisted twice, but all properties but the
        ones set by LifecycleCallbacks are deleted.
        */

        $user->Memberships->add($membership);
        $group->Memberships->add($membership);

        $this->_em->flush();

        $this->assertEquals(1, $membership->prePersistCallCount);
        $this->assertEquals(0, $membership->preUpdateCallCount);
        $this->assertInstanceOf('DateTime', $membership->updated);
    }
}

/**
 * @Entity
 */
class DDC345User
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    public $id;

    /**
     * @var string
     * @Column(type="string")
     */
    public $name;

    /** @OneToMany(targetEntity="DDC345Membership", mappedBy="user", cascade={"persist"}) */
    public $Memberships;

    public function __construct()
    {
        $this->Memberships = new ArrayCollection();
    }
}

/**
 * @Entity
 */
class DDC345Group
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    public $id;

    /**
     * @var string
     * @Column(type="string")
     */
    public $name;

    /** @OneToMany(targetEntity="DDC345Membership", mappedBy="group", cascade={"persist"}) */
    public $Memberships;

    public function __construct()
    {
        $this->Memberships = new ArrayCollection();
    }
}

/**
 * @Entity
 * @HasLifecycleCallbacks
 * @Table(name="ddc345_memberships", uniqueConstraints={
 *      @UniqueConstraint(name="ddc345_memship_fks", columns={"user_id","group_id"})
 * })
 */
class DDC345Membership
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    public $id;

    /**
     * @OneToOne(targetEntity="DDC345User", inversedBy="Memberships")
     * @JoinColumn(name="user_id", referencedColumnName="id", nullable=false)
     */
    public $user;

    /**
     * @OneToOne(targetEntity="DDC345Group", inversedBy="Memberships")
     * @JoinColumn(name="group_id", referencedColumnName="id", nullable=false)
     */
    public $group;

    /**
     * @var string
     * @Column(type="string")
     */
    public $state;

    /** @Column(type="datetime") */
    public $updated;

    public $prePersistCallCount = 0;
    public $preUpdateCallCount  = 0;

    /** @PrePersist */
    public function doStuffOnPrePersist(): void
    {
        //echo "***** PrePersist\n";
        ++$this->prePersistCallCount;
        $this->updated = new DateTime();
    }

    /** @PreUpdate */
    public function doStuffOnPreUpdate(): void
    {
        //echo "***** PreUpdate\n";
        ++$this->preUpdateCallCount;
        $this->updated = new DateTime();
    }
}
