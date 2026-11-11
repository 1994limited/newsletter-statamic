<?php

namespace NineteenNinetyFour\Newsletter\Tests\Feature;

use NineteenNinetyFour\Newsletter\Models\Subscriber;
use NineteenNinetyFour\Newsletter\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Addon;

class ControlPanelTest extends TestCase
{
    /**
     * @return array<int, array<int, string>>
     */
    protected function csv(string $content): array
    {
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        $handle = fopen('php://memory', 'w+');
        fwrite($handle, substr($content, 3));
        rewind($handle);
        $rows = [];

        while (($row = fgetcsv($handle, escape: '')) !== false) {
            $rows[] = $row;
        }

        return $rows;
    }

    #[Test]
    public function the_list_with_counts_search_and_filter(): void
    {
        $this->subscribed('ada@example.com', 'Ada Reader');
        $this->subscribed('bo@example.com');
        $this->pending('waiting@example.com');
        $this->newsletter()->unsubscribe($this->subscribed('gone@example.com'));

        $this->actingAs($this->user(super: true))
            ->get(cp_route('newsletter.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('newsletter::Index')
                ->where('counts', ['pending' => 1, 'subscribed' => 2, 'unsubscribed' => 1])
                ->has('subscribers', 4)
                ->where('canExport', true)
                ->where('askName', true));

        $this->get(cp_route('newsletter.index', ['status' => 'pending']))
            ->assertInertia(fn ($page) => $page->has('subscribers', 1)->where('subscribers.0.email', 'waiting@example.com'));

        $this->get(cp_route('newsletter.index', ['search' => 'reader']))
            ->assertInertia(fn ($page) => $page->has('subscribers', 1)->where('subscribers.0.email', 'ada@example.com'));
    }

    #[Test]
    public function the_list_needs_permission(): void
    {
        $this->actingAs($this->user(['access cp']))->getJson(cp_route('newsletter.index'))->assertForbidden();

        $this->actingAs($this->user(['view newsletter']))
            ->get(cp_route('newsletter.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canExport', false)->where('canManage', false));
    }

    #[Test]
    public function download_csv_is_the_confirmed_list_by_default(): void
    {
        $this->subscribed('ada@example.com', 'Ada Reader');
        $this->pending('waiting@example.com');
        $this->newsletter()->unsubscribe($this->subscribed('gone@example.com'));

        $response = $this->actingAs($this->user(['view newsletter', 'export newsletter']))->get(cp_route('newsletter.export'));

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('newsletter-subscribed-', $response->headers->get('Content-Disposition'));

        $rows = $this->csv($response->streamedContent());
        $this->assertSame(['Email', 'Name', 'Status', 'Site', 'Signed up', 'Confirmed', 'Unsubscribed', 'Agreed to', 'Form page', 'Unsubscribe link'], $rows[0]);
        $this->assertCount(2, $rows);
        $this->assertSame(['ada@example.com', 'Ada Reader', 'subscribed', 'default'], array_slice($rows[1], 0, 4));
        $this->assertNotSame('', $rows[1][5]);
        $this->assertSame('Email me the newsletter.', $rows[1][7]);
        $this->assertStringContainsString('/!/newsletter/unsubscribe/', $rows[1][9]);
        $this->assertStringContainsString('signature=', $rows[1][9]);

        // The link in the CSV works.
        $this->get($rows[1][9])->assertOk()->assertSee('Stop sending the newsletter to ada@example.com?');
    }

    #[Test]
    public function download_csv_by_status_and_search(): void
    {
        $this->subscribed('ada@example.com');
        $this->subscribed('bo@example.com');
        $this->pending('waiting@example.com');
        $this->actingAs($this->user(super: true));

        $this->assertCount(4, $this->csv($this->get(cp_route('newsletter.export', ['status' => 'all']))->streamedContent()));
        $this->assertCount(2, $this->csv($this->get(cp_route('newsletter.export', ['status' => 'pending']))->streamedContent()));
        $this->assertCount(2, $this->csv($this->get(cp_route('newsletter.export', ['search' => 'bo@']))->streamedContent()));
        $this->assertCount(3, $this->csv($this->get(cp_route('newsletter.export', ['status' => 'nonsense']))->streamedContent()));
    }

    #[Test]
    public function cells_are_never_read_as_formulas(): void
    {
        $this->subscribed('ada@example.com', '=HYPERLINK("https://evil.example")');

        $rows = $this->csv($this->actingAs($this->user(super: true))->get(cp_route('newsletter.export'))->streamedContent());

        $this->assertSame('\'=HYPERLINK("https://evil.example")', $rows[1][1]);
    }

    #[Test]
    public function download_needs_permission(): void
    {
        $this->subscribed();

        $this->actingAs($this->user(['view newsletter']))->getJson(cp_route('newsletter.export'))->assertForbidden();
    }

    #[Test]
    public function deleting_a_subscriber(): void
    {
        $subscriber = $this->subscribed();

        $this->actingAs($this->user(['view newsletter']))->deleteJson(cp_route('newsletter.destroy', $subscriber->id))->assertForbidden();
        $this->assertSame(1, Subscriber::count());

        $this->actingAs($this->user(['view newsletter', 'manage newsletter']))->delete(cp_route('newsletter.destroy', $subscriber->id))->assertRedirect();
        $this->assertSame(0, Subscriber::count());
    }

    #[Test]
    public function the_settings_toggles_show_what_the_config_does(): void
    {
        $fields = fn () => Addon::get('1994/newsletter-statamic')->settingsBlueprint();

        $this->assertTrue($fields()->field('consent')->defaultValue());
        $this->assertFalse($fields()->field('ask_name')->defaultValue());

        config(['newsletter.consent' => false, 'newsletter.ask_name' => true]);
        app()->forgetScopedInstances();

        $this->assertFalse($fields()->field('consent')->defaultValue());
        $this->assertTrue($fields()->field('ask_name')->defaultValue());
    }
}
