<?php

use App\Concert;
use App\Billing\PaymentGateway;
use App\Billing\FakePaymentGateway;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class PurchaseTicketsTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(){
        parent::setUp();

        $this->paymentGateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $this->paymentGateway);
    }

    /** @test */
    function customer_can_purchase_concert_tickets()
    {
        // Arrange
        // Create Concert
        $concert = factory(Concert::class)->create([
            'ticket_price' => 3250
        ]);

        // Act
        // Purchase concert Tickets
        $this->json('POST', "/concerts/{$concert->id}/orders", [
            'email'             => 'john@example.com',
            'ticket_quantity'   => 3,
            'payment_token'     => $this->paymentGateway->getValidTestToken()
        ]);

        // Assert
        $this->assertResponseStatus(201);
        // Make sure the customer was charged the correct ammount
        $this->assertEquals(9750, $this->paymentGateway->totalCharges());

        // Make sure that an order exists for this customer
        $order = $concert->orders()->where('email', 'john@example.com')->first();
        $this->assertNotNull($order);

        // Ensure order has 3 tickets
        $this->assertEquals(3, $order->tickets()->count());
    }

    /** @test */
    function email_is_required_to_purchase_tickets()
    {
        $concert = factory(Concert::class)->create();

        $this->json('POST', "/concerts/{$concert->id}/orders", [
            'ticket_quantity'   => 3,
            'payment_token'     => $this->paymentGateway->getValidTestToken()
        ]);

        $this->assertResponseStatus(422);

        $this->assertArrayHasKey('email', $this->decodeResponseJson());
    }
}