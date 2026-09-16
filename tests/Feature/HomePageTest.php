<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_root_loads_the_votetrust_landing_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Run elections people can trust.')
            ->assertSee('Why VoteTrust')
            ->assertSee('How it works')
            ->assertSee('For organisers')
            ->assertSee('For candidates')
            ->assertSee('For voters')
            ->assertDontSee('Goh Chun Wen')
            ->assertDontSee('Chew Zhexuan')
            ->assertDontSee('Wong Shun Bin')
            ->assertDontSee('Low Wen Kai');
    }

    public function test_guest_landing_footer_links_to_faq_login_and_registration(): void
    {
        $response = $this->get('/')->assertOk();

        $document = new \DOMDocument;
        $previousState = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML((string) $response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        self::assertTrue($loaded);

        $xpath = new \DOMXPath($document);

        foreach ([route('faq'), route('login'), route('register')] as $url) {
            $links = $xpath->query(sprintf('//footer//a[@href="%s"]', $url));

            self::assertNotFalse($links);
            self::assertGreaterThan(0, $links->length, "The landing-page footer does not link to {$url}.");
        }
    }

    public function test_the_public_faq_page_explains_frequently_asked_questions(): void
    {
        $response = $this->get(route('faq'))
            ->assertOk()
            ->assertSee('Frequently asked questions');

        $document = new \DOMDocument;
        $previousState = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML((string) $response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        self::assertTrue($loaded);

        $xpath = new \DOMXPath($document);
        $faqItems = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " faq-list ")]/details');
        $openItems = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " faq-list ")]/details[@open]');

        self::assertNotFalse($faqItems);
        self::assertNotFalse($openItems);
        self::assertCount(6, $faqItems);
        self::assertCount(1, $openItems);
        self::assertTrue($faqItems->item(0)?->isSameNode($openItems->item(0)) ?? false);

        foreach ($faqItems as $faqItem) {
            $summaries = $xpath->query('./summary[normalize-space()]', $faqItem);
            $answerInners = $xpath->query(
                './div[contains(concat(" ", normalize-space(@class), " "), " faq-answer ")]'
                .'/div[contains(concat(" ", normalize-space(@class), " "), " faq-answer__inner ")]',
                $faqItem,
            );

            self::assertNotFalse($summaries);
            self::assertNotFalse($answerInners);
            self::assertCount(1, $summaries);
            self::assertCount(1, $answerInners);
        }
    }

    public function test_an_authenticated_member_visiting_the_root_is_sent_to_their_dashboard(): void
    {
        $member = User::factory()->create(['is_admin' => false]);

        $this->actingAs($member)
            ->get('/')
            ->assertRedirect(route('dashboard'))
            ->assertDontSee('Run elections people can trust.');
    }

    public function test_an_authenticated_administrator_visiting_the_root_is_sent_to_the_admin_dashboard(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);

        $this->actingAs($administrator)
            ->get('/')
            ->assertRedirect(route('admin.dashboard'))
            ->assertDontSee('Run elections people can trust.');
    }
}
