<?php

namespace Tests\Feature;

use App\Models\DebitCard;
use App\Models\User;
use Database\Factories\DebitCardFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DebitCardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCards()
    {
        $debitCards = DebitCard::factory(2)->active()->create([
            'user_id' => $this->user->id
        ]);

        $this->getJson(route('debit-cards.index'))
            ->assertJsonFragment([
                "id" => $debitCards->first()->id,
                "number" => $debitCards->first()->number,
                "type" => $debitCards->first()->type,
                "expiration_date" => Carbon::parse($debitCards->first()->expiration_date)
                    ->format('Y-m-d H:i:s'),
                "is_active" => $debitCards->first()->is_active,
            ])->assertJsonFragment([
                "id" => $debitCards->last()->id,
                "number" => $debitCards->last()->number,
                "type" => $debitCards->last()->type,
                "expiration_date" => Carbon::parse($debitCards->last()->expiration_date)
                    ->format('Y-m-d H:i:s'),
                "is_active" => $debitCards->last()->is_active,
            ]);
    }

    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        $debitCard = DebitCard::factory()->active()->create([
            'user_id' => $this->user->id
        ]);

        $otherUser = User::factory()->create();

        $debitCardsOfOtherUser = DebitCard::factory(2)->active()->create([
            'user_id' => $otherUser->id
        ]);


        $this->getJson(route('debit-cards.index'))
            ->assertJsonFragment([
                "id" => $debitCard->first()->id,
                "number" => $debitCard->first()->number,
                "type" => $debitCard->first()->type,
                "expiration_date" => Carbon::parse($debitCard->first()->expiration_date)
                    ->format('Y-m-d H:i:s'),
                "is_active" => $debitCard->first()->is_active,
            ])->assertJsonMissing([
                "id" => $debitCardsOfOtherUser->last()->id,
                "number" => $debitCardsOfOtherUser->last()->number,
            ])->assertJsonMissing([
                "id" => $debitCardsOfOtherUser->last()->id,
                "number" => $debitCardsOfOtherUser->last()->number
            ]);
    }

    public function testCustomerCanCreateADebitCard()
    {
        $request = [
            'type' => Str::random()
        ];

        $this->assertDatabaseMissing('debit_cards', [
            'type' => $request['type'],
            'expiration_date' => Carbon::now()->addYear(),
            'user_id' => $this->user->id,
        ]);

        $this->postJson(route('debit-cards.store'), $request);

        $this->assertDatabaseHas('debit_cards', [
            'type' => $request['type'],
            'expiration_date' => Carbon::now()->addYear(),
            'user_id' => $this->user->id,
        ]);
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        $debitCards = DebitCard::factory(2)->active()->create([
            'user_id' => $this->user->id
        ]);

        $this->getJson(route('debit-cards.show', $debitCards->first()))
            ->assertJsonFragment([
                "id" => $debitCards->first()->id,
                "number" => $debitCards->first()->number,
                "type" => $debitCards->first()->type,
                "expiration_date" => Carbon::parse($debitCards->first()->expiration_date)
                    ->format('Y-m-d H:i:s'),
                "is_active" => $debitCards->first()->is_active,
            ])->assertJsonMissing([
                "id" => $debitCards->last()->id,
                "number" => $debitCards->last()->number,
            ]);


        $this->getJson(route('debit-cards.show', $debitCards->last()))
            ->assertJsonFragment([
                "id" => $debitCards->last()->id,
                "number" => $debitCards->last()->number,
                "type" => $debitCards->last()->type,
                "expiration_date" => Carbon::parse($debitCards->last()->expiration_date)
                    ->format('Y-m-d H:i:s'),
                "is_active" => $debitCards->last()->is_active,
            ])->assertJsonMissing([
                "id" => $debitCards->first()->id,
                "number" => $debitCards->first()->number,
            ]);
    }

    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        $debitCards = DebitCard::factory(2)->active()->create([
            'user_id' => $this->user->id
        ]);

        $this->getJson(route('debit-cards.show', $debitCards->first()))
            ->assertJsonFragment([
                "id" => $debitCards->first()->id,
                "number" => $debitCards->first()->number,
                "type" => $debitCards->first()->type,
                "expiration_date" => Carbon::parse($debitCards->first()->expiration_date)
                    ->format('Y-m-d H:i:s'),
                "is_active" => $debitCards->first()->is_active,
            ])->assertJsonMissing([
                "id" => $debitCards->last()->id,
                "number" => $debitCards->last()->number,
            ]);
    }

    public function testCustomerCanActivateADebitCard()
    {
        $debitCard = DebitCard::factory()->expired()->create([
            'user_id' => $this->user->id
        ]);

        $this->assertNotNull($debitCard->disabled_at);

        $this->putJson(route('debit-cards.update', $debitCard), [
            'is_active' => true
        ]);

        $this->assertNull($debitCard->fresh()->disabled_at);
    }

    public function testCustomerCanDeactivateADebitCard()
    {
        $debitCard = DebitCard::factory()->active()->create([
            'user_id' => $this->user->id
        ]);

        $this->assertNull($debitCard->disabled_at);

        $this->putJson(route('debit-cards.update', $debitCard), [
            'is_active' => false
        ]);

        $this->assertNotNull($debitCard->fresh()->disabled_at);
    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        $debitCard = DebitCard::factory()->active()->create([
            'user_id' => $this->user->id
        ]);

        $this->assertNull($debitCard->disabled_at);

        $this->putJson(route('debit-cards.update', $debitCard), [
            'is_active' => 'sdkfjskdf'
        ])->assertJson([
            "message" => "The given data was invalid.",
            "errors" => [
                "is_active" => [
                    "The is active field must be true or false."
                ]
            ]
        ]);


        $this->putJson(route('debit-cards.update', $debitCard), [
            'something_else' => false
        ])->assertJson([
                "message" => "The given data was invalid.",
                "errors" => [
                    "is_active" => [
                        "The is active field is required."
                    ]
                ]
            ]);
    }

    public function testCustomerCanDeleteADebitCard()
    {
        $debitCard = DebitCard::factory()->active()->create([
            'user_id' => $this->user->id
        ]);

        $this->assertDatabaseHas('debit_cards', [
            'id' => $debitCard->id,
            'type' => $debitCard->type,
            'expiration_date' => $debitCard->expiration_date,
            'user_id' => $debitCard->user_id,
        ]);

        $this->deleteJson(route('debit-cards.destroy', $debitCard));

        $this->assertSoftDeleted($debitCard);
    }

    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        $debitCard = DebitCard::factory()->active()->hasDebitCardTransactions(2)->create([
            'user_id' => $this->user->id
        ]);

        $this->assertDatabaseHas('debit_cards', [
            'id' => $debitCard->id,
            'type' => $debitCard->type,
            'expiration_date' => $debitCard->expiration_date,
            'user_id' => $debitCard->user_id,
        ]);

        $this->deleteJson(route('debit-cards.destroy', $debitCard));

        $this->assertNotSoftDeleted($debitCard);
    }

    // Extra bonus for extra tests :)
}
