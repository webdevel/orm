<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Tests\OrmFunctionalTestCase;
use Exception;

use function count;

class DDC735Test extends OrmFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        try {
            $this->_schemaTool->createSchema(
                [
                    $this->_em->getClassMetadata(DDC735Product::class),
                    $this->_em->getClassMetadata(DDC735Review::class),
                ]
            );
        } catch (Exception $e) {
        }
    }

    public function testRemoveElement_AppliesOrphanRemoval(): void
    {
        // Create a product and its first review
        $product = new DDC735Product();
        $review  = new DDC735Review($product);

        // Persist and flush
        $this->_em->persist($product);
        $this->_em->flush();

        // Now you see it
        $this->assertEquals(1, count($product->getReviews()));

        // Remove the review
        $reviewId = $review->getId();
        $product->removeReview($review);
        $this->_em->flush();

        // Now you don't
        $this->assertEquals(0, count($product->getReviews()), 'count($reviews) should be 0 after removing its only Review');

        // Refresh
        $this->_em->refresh($product);

        // It should still be 0
        $this->assertEquals(0, count($product->getReviews()), 'count($reviews) should still be 0 after the refresh');

        // Review should also not be available anymore
        $this->assertNull($this->_em->find(DDC735Review::class, $reviewId));
    }
}

/**
 * @Entity
 */
class DDC735Product
{
    /** @Id @Column(type="integer") @GeneratedValue */
    protected $id;

    /**
     * @OneToMany(
     *   targetEntity="DDC735Review",
     *   mappedBy="product",
     *   cascade={"persist"},
     *   orphanRemoval=true
     * )
     */
    protected $reviews;

    public function __construct()
    {
        $this->reviews = new ArrayCollection();
    }

    public function getReviews()
    {
        return $this->reviews;
    }

    public function addReview(DDC735Review $review): void
    {
        $this->reviews->add($review);
    }

    public function removeReview(DDC735Review $review): void
    {
        $this->reviews->removeElement($review);
    }
}

/**
 * @Entity
 */
class DDC735Review
{
    /** @Id @Column(type="integer") @GeneratedValue */
    protected $id;

    /** @ManyToOne(targetEntity="DDC735Product", inversedBy="reviews") */
    protected $product;

    public function __construct(DDC735Product $product)
    {
        $this->product = $product;
        $product->addReview($this);
    }

    public function getId()
    {
        return $this->id;
    }
}
