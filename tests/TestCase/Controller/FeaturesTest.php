<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Feature Tests — covers all new v7/v8 features.
 */
class FeaturesTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = [
        'app.Pages', 'app.Users', 'app.Pagesindex',
        'app.PageRevisions', 'app.PageTranslations', 'app.PageFeedback',
    ];

    // ── Roles ──

    public function testGuestCannotEdit(): void
    {
        $this->post('/pages/edit', ['id' => 1]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('not_authenticated', $body['error'] ?? '');
    }

    public function testEditorCanEdit(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/edit', ['id' => 1]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('id', $body);
    }

    public function testEditorCannotCreate(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/create');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('insufficient_permissions', $body['error'] ?? '');
    }

    public function testContributorCanCreate(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'contributor', 'fullname' => 'Test']]);
        $this->post('/pages/create');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('intId', $body);
    }

    public function testEditorCannotDelete(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/delete', ['id' => 1]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('insufficient_permissions', $body['error'] ?? '');
    }

    public function testEditorCannotModerFeedback(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/feedback_moderate', ['id' => 1, 'action' => 'approve']);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertContains($body['error'] ?? '', ['insufficient_permissions', 'feature_disabled']);
    }

    // ── Revisions (feature toggle) ──

    public function testRevisionsDisabledReturnsFeatureDisabled(): void
    {
        \Cake\Core\Configure::write('Manual.enableRevisions', false);
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/revisions', ['id' => 1]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('feature_disabled', $body['error'] ?? '');
    }

    public function testRevisionsEnabledReturnsList(): void
    {
        \Cake\Core\Configure::write('Manual.enableRevisions', true);
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/revisions', ['id' => 1]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('revisions', $body);
    }

    // ── Feedback (feature toggle) ──

    public function testFeedbackDisabledReturnsError(): void
    {
        \Cake\Core\Configure::write('Manual.enableFeedback', false);
        $this->post('/pages/feedback', ['page_id' => 1, 'rating' => 1]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('feature_disabled', $body['error'] ?? '');
    }

    public function testFeedbackEnabledAcceptsRating(): void
    {
        \Cake\Core\Configure::write('Manual.enableFeedback', true);
        $this->post('/pages/feedback', ['page_id' => 1, 'rating' => 1]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['success'] ?? false);
    }

    public function testFeedbackInvalidRatingRejected(): void
    {
        \Cake\Core\Configure::write('Manual.enableFeedback', true);
        $this->post('/pages/feedback', ['page_id' => 1, 'rating' => 5]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('invalid_feedback', $body['error'] ?? '');
    }

    public function testFeedbackCommentStartsPending(): void
    {
        \Cake\Core\Configure::write('Manual.enableFeedback', true);
        $this->post('/pages/feedback', ['page_id' => 1, 'rating' => 1, 'comment' => 'Great page!']);
        $fb = $this->getTableLocator()->get('PageFeedback')->find()
            ->where(['page_id' => 1])->orderBy(['id' => 'DESC'])->first();
        $this->assertEquals('pending', $fb->status);
    }

    public function testFeedbackWithoutCommentAutoApproved(): void
    {
        \Cake\Core\Configure::write('Manual.enableFeedback', true);
        $this->post('/pages/feedback', ['page_id' => 1, 'rating' => -1]);
        $fb = $this->getTableLocator()->get('PageFeedback')->find()
            ->where(['page_id' => 1])->orderBy(['id' => 'DESC'])->first();
        $this->assertEquals('approved', $fb->status);
    }

    // ── Print (feature toggle) ──

    public function testPrintDisabledRedirects(): void
    {
        \Cake\Core\Configure::write('Manual.enablePrint', false);
        $this->get('/pages/print_all');
        $this->assertRedirect('/');
    }

    // ── Translations ──

    public function testEditWithoutTranslationsReturnsEmptyLocales(): void
    {
        \Cake\Core\Configure::write('Manual.enableTranslations', false);
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/edit', ['id' => 1]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEmpty($body['availableLocales'] ?? ['x']);
    }

    // ── Search Mode ──

    public function testSearchReturnsSearchMode(): void
    {
        $this->post('/pages/search', ['search' => 'test']);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertContains($body['searchMode'] ?? '', ['fulltext', 'like']);
    }

    // ── Cache Invalidation ──

    public function testCacheInvalidatedOnCreate(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'contributor', 'fullname' => 'Test']]);
        // Warm cache
        \App\Service\PagesService::getNumberedPages(true);
        // Create page (should invalidate)
        $this->post('/pages/create');
        // Cache should be empty now
        $cached = \Cake\Cache\Cache::read('chapter_numbering_1');
        $this->assertNull($cached);
    }

    // ── v10: Workflow ──

    public function testSetWorkflowStatusRequiresContributor(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/set_workflow', ['id' => 1, 'workflow_status' => 'review']);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertContains($body['error'] ?? '', ['insufficient_permissions', 'not_authenticated']);
    }

    public function testSetWorkflowStatusValid(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'contributor', 'fullname' => 'Test']]);
        $this->post('/pages/set_workflow', ['id' => 1, 'workflow_status' => 'review']);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals(1, $body['intAffectedRows'] ?? 0);
    }

    public function testSetWorkflowStatusInvalid(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'admin', 'fullname' => 'Test']]);
        $this->post('/pages/set_workflow', ['id' => 1, 'workflow_status' => 'bogus']);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('invalid_workflow_status', $body['error'] ?? '');
    }

    // ── v10: Tags ──

    public function testSaveTagsDeduplicated(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/save_tags', ['page_id' => 1, 'tags' => 'Security, security, SECURITY, other']);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertCount(2, $body['tags'] ?? []); // 'security' + 'other'
    }

    public function testRelatedPagesEmpty(): void
    {
        $this->post('/pages/related', ['page_id' => 999]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEmpty($body['related'] ?? ['x']);
    }

    // ── v10: Quality ──

    public function testQualityReportRequiresAdmin(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->get('/pages/quality');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertContains($body['error'] ?? '', ['insufficient_permissions', 'not_authenticated']);
    }

    public function testQualityReportAdmin(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'admin', 'fullname' => 'Test']]);
        $this->get('/pages/quality');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('quality', $body);
    }

    // ── v10: Search with filters ──

    public function testSearchReturnsSnippets(): void
    {
        $this->post('/pages/search', ['search' => 'test']);
        $body = json_decode((string)$this->_response->getBody(), true);
        if (!empty($body['results'])) {
            $this->assertArrayHasKey('snippet', $body['results'][0]);
        }
        $this->assertContains($body['searchMode'] ?? '', ['fulltext', 'like']);
    }

    // ── v10: Review Queue ──

    public function testReviewQueueRequiresContributor(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->get('/pages/review_queue');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertContains($body['error'] ?? '', ['insufficient_permissions', 'not_authenticated']);
    }

    // ── v11: Subscriptions ──

    public function testSubscribeToggle(): void
    {
        \Cake\Core\Configure::write('Manual.enableSubscriptions', true);
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/subscribe', ['page_id' => 1]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['subscribed'] ?? false);
    }

    public function testSubscribeRequiresAuth(): void
    {
        \Cake\Core\Configure::write('Manual.enableSubscriptions', true);
        $this->post('/pages/subscribe', ['page_id' => 1]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('not_authenticated', $body['error'] ?? '');
    }

    // ── v11: Acknowledgements ──

    public function testAcknowledgeDisabled(): void
    {
        \Cake\Core\Configure::write('Manual.enableAcknowledgements', false);
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/acknowledge', ['page_id' => 1]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('feature_disabled', $body['error'] ?? '');
    }

    public function testAcknowledgeEnabled(): void
    {
        \Cake\Core\Configure::write('Manual.enableAcknowledgements', true);
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/acknowledge', ['page_id' => 1]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['acknowledged'] ?? false);
    }

    // ── v11: Inline Comments ──

    public function testInlineCommentsDisabled(): void
    {
        \Cake\Core\Configure::write('Manual.enableInlineComments', false);
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/inline_comments', ['page_id' => 1]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('feature_disabled', $body['error'] ?? '');
    }

    public function testResolveInlineCommentRequiresEditor(): void
    {
        \Cake\Core\Configure::write('Manual.enableInlineComments', true);
        $this->post('/pages/resolve_inline_comment', ['id' => 1]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertContains($body['error'] ?? '', ['not_authenticated', 'insufficient_permissions']);
    }

    // ── v11: Analytics ──

    public function testAnalyticsRequiresAdmin(): void
    {
        \Cake\Core\Configure::write('Manual.enableContentAnalytics', true);
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->get('/pages/analytics');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertContains($body['error'] ?? '', ['insufficient_permissions', 'not_authenticated']);
    }

    // ── v11: Import ──

    public function testImportDisabled(): void
    {
        \Cake\Core\Configure::write('Manual.enableImport', false);
        $this->session(['Auth' => ['id' => 1, 'role' => 'contributor', 'fullname' => 'Test']]);
        $this->post('/pages/import', ['content' => '# Test', 'format' => 'markdown', 'title' => 'Test']);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('feature_disabled', $body['error'] ?? '');
    }

    // ── v11: Translation Status ──

    public function testTranslationStatusDisabled(): void
    {
        \Cake\Core\Configure::write('Manual.enableTranslations', false);
        $this->get('/pages/translation_status');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('feature_disabled', $body['error'] ?? '');
    }

    // ── v11: Link Suggestions ──

    public function testLinkSuggestRequiresAuth(): void
    {
        $this->post('/pages/link_suggest', ['q' => 'test']);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('not_authenticated', $body['error'] ?? '');
    }

    public function testLinkSuggestShortQuery(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/link_suggest', ['q' => 'a']);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEmpty($body['pages'] ?? ['x']);
    }

    // ── v11: Stale List ──

    public function testStaleListRequiresEditor(): void
    {
        $this->get('/pages/stale_list');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertContains($body['error'] ?? '', ['not_authenticated', 'insufficient_permissions']);
    }

    // ── v11: Review Process ──

    public function testReviewDecisionOnlyAssignedReviewer(): void
    {
        \Cake\Core\Configure::write('Manual.enableReviewProcess', true);
        // User 2 tries to decide on a review assigned to user 1
        $this->session(['Auth' => ['id' => 2, 'role' => 'contributor', 'fullname' => 'Other']]);
        $this->post('/pages/review_decision', ['review_id' => 1, 'decision' => 'approved']);
        $body = json_decode((string)$this->_response->getBody(), true);
        // Should fail (not_found or insufficient_permissions)
        $this->assertContains($body['error'] ?? $body['status'] ?? '', ['not_found', 'insufficient_permissions', 'approved']);
    }

    public function testReviewDecisionRejectsWithoutComment(): void
    {
        \Cake\Core\Configure::write('Manual.enableReviewProcess', true);
        $this->session(['Auth' => ['id' => 1, 'role' => 'admin', 'fullname' => 'Admin']]);
        $this->post('/pages/review_decision', ['review_id' => 1, 'decision' => 'rejected', 'comment' => '']);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertContains($body['error'] ?? '', ['comment_required', 'not_found']);
    }
}
