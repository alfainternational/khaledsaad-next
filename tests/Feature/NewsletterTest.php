<?php

namespace Tests\Feature;

use App\Domain\Content\Models\NewsletterSubscriber;
use App\Mail\NewsletterConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** النشرة البريدية (§3.2): مزدوجة الموافقة، بريد في الطابور، تأكيد وإلغاء بالرمز، وثرتل. */
class NewsletterTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscribing_stores_the_address_and_queues_a_confirmation_mail(): void
    {
        Mail::fake();
        $this->from('/articles')->post('/newsletter', ['email' => 'Reader@Example.com'])
            ->assertRedirect('/articles#newsletter')->assertSessionHas('newsletter');
        $subscriber = NewsletterSubscriber::query()->where('email', 'reader@example.com')->firstOrFail();
        $this->assertNull($subscriber->confirmed_at);
        $this->assertNotEmpty($subscriber->token);
        Mail::assertQueued(NewsletterConfirmation::class, fn (NewsletterConfirmation $mail) => $mail->token === $subscriber->token && $mail->hasTo('reader@example.com'));
        $this->assertTrue((new NewsletterConfirmation('t')) instanceof \Illuminate\Contracts\Queue\ShouldQueue);

        // JSON for the inline footer flow (no full reload).
        $this->postJson('/newsletter', ['email' => 'second@example.com'])->assertOk()->assertJson(['ok' => true]);
        $this->postJson('/newsletter', ['email' => 'not-an-email'])->assertStatus(422);
        // Honeypot filled => validation error, nothing stored.
        $this->post('/newsletter', ['email' => 'bot@example.com', 'website' => 'http://spam'])->assertSessionHasErrors('website');
        $this->assertDatabaseMissing('newsletter_subscribers', ['email' => 'bot@example.com']);
    }

    public function test_confirm_and_unsubscribe_links_work_and_resubscribing_reactivates(): void
    {
        Mail::fake();
        $this->post('/newsletter', ['email' => 'a@example.com']);
        $subscriber = NewsletterSubscriber::query()->firstOrFail();

        $this->get('/newsletter/confirm/'.$subscriber->token)->assertOk()->assertSee('تم تأكيد اشتراكك');
        $this->assertNotNull($subscriber->fresh()->confirmed_at);
        $this->assertTrue($subscriber->fresh()->isActive());
        // Already active: no second mail.
        Mail::fake();
        $this->post('/newsletter', ['email' => 'a@example.com'])->assertSessionHas('newsletter', 'هذا البريد مشترك بالفعل في النشرة.');
        Mail::assertNothingQueued();

        $this->get('/newsletter/unsubscribe/'.$subscriber->token)->assertOk()->assertSee('تم إلغاء الاشتراك');
        $this->assertFalse($subscriber->fresh()->isActive());
        $this->get('/newsletter/confirm/bad-token')->assertOk()->assertSee('غير صالح');

        // Re-subscribing after unsubscribe mints a new token and asks for confirmation again.
        Mail::fake();
        $this->post('/newsletter', ['email' => 'a@example.com']);
        $fresh = $subscriber->fresh();
        $this->assertNull($fresh->confirmed_at);
        $this->assertNull($fresh->unsubscribed_at);
        $this->assertNotSame($subscriber->token, $fresh->token);
        Mail::assertQueued(NewsletterConfirmation::class);
    }

    public function test_the_mail_contains_both_links_and_the_endpoint_is_throttled(): void
    {
        $mail = new NewsletterConfirmation('tok123');
        $rendered = $mail->render();
        $this->assertStringContainsString(route('newsletter.confirm', 'tok123'), $rendered);
        $this->assertStringContainsString(route('newsletter.unsubscribe', 'tok123'), $rendered);
        $this->assertSame('أكّد اشتراكك في نشرة أثر', $mail->envelope()->subject);

        Mail::fake();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/newsletter', ['email' => "u{$i}@example.com"])->assertRedirect();
        }
        $this->post('/newsletter', ['email' => 'u9@example.com'])->assertStatus(429);
    }
}
