<?php

namespace Tests\Unit;

use App\Models\CareerFact;
use App\Models\DiscoveredVacancy;
use App\Models\User;
use App\Services\Discovery\FastFitScorer;
use App\Services\TrustedCareerQuery;
use Mockery;
use Tests\TestCase;

class FastFitScorerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_returns_neutral_score_when_no_career_facts_exist(): void
    {
        $user = new User(['id' => '01HQTESTUSER000000000000001']);

        $trustedMock = Mockery::mock(TrustedCareerQuery::class);
        $trustedMock->shouldReceive('forMatching')
            ->once()
            ->with($user)
            ->andReturn(['facts' => [], 'claims' => []]);

        $scorer = new FastFitScorer($trustedMock);

        $result = $scorer->score($user, 'Senior Laravel Developer', 'We need a PHP developer.');

        $this->assertSame(50, $result['score']);
        $this->assertSame(DiscoveredVacancy::FIT_MEDIUM, $result['level']);
        $this->assertEmpty($result['reasons']['matched_skills']);
        $this->assertFalse($result['reasons']['title_match']);
    }

    public function test_scores_high_when_role_and_skills_match(): void
    {
        $user = new User(['id' => '01HQTESTUSER000000000000002']);

        $fact1 = new CareerFact([
            'assertion_approved' => 'Senior Backend Engineer with 7 years experience in PHP and Laravel.',
            'status' => CareerFact::STATUS_CONFIRMED,
        ]);
        $fact2 = new CareerFact([
            'assertion_approved' => 'Proficient in PostgreSQL, Docker, Redis, and Microservices architecture.',
            'status' => CareerFact::STATUS_CONFIRMED,
        ]);

        $trustedMock = Mockery::mock(TrustedCareerQuery::class);
        $trustedMock->shouldReceive('forMatching')
            ->once()
            ->with($user)
            ->andReturn(['facts' => [$fact1, $fact2], 'claims' => []]);

        $scorer = new FastFitScorer($trustedMock);

        $result = $scorer->score(
            $user,
            'Senior Backend Developer (PHP/Laravel)',
            'Looking for experienced engineer skilled in Laravel, PostgreSQL and Docker.'
        );

        $this->assertGreaterThanOrEqual(70, $result['score']);
        $this->assertSame(DiscoveredVacancy::FIT_HIGH, $result['level']);
        $this->assertTrue($result['reasons']['title_match']);
        $this->assertContains('Laravel', $result['reasons']['matched_skills']);
        $this->assertContains('Docker', $result['reasons']['matched_skills']);
    }

    public function test_scores_low_for_unrelated_stack(): void
    {
        $user = new User(['id' => '01HQTESTUSER000000000000003']);

        $fact = new CareerFact([
            'assertion_approved' => 'Data Scientist proficient in Python, PyTorch, and NLP.',
            'status' => CareerFact::STATUS_CONFIRMED,
        ]);

        $trustedMock = Mockery::mock(TrustedCareerQuery::class);
        $trustedMock->shouldReceive('forMatching')
            ->once()
            ->with($user)
            ->andReturn(['facts' => [$fact], 'claims' => []]);

        $scorer = new FastFitScorer($trustedMock);

        $result = $scorer->score(
            $user,
            'Java Spring Boot Developer',
            'Must know Spring, Kubernetes, Kafka, and Kotlin.'
        );

        $this->assertLessThan(40, $result['score']);
        $this->assertSame(DiscoveredVacancy::FIT_LOW, $result['level']);
        $this->assertFalse($result['reasons']['title_match']);
        $this->assertEmpty($result['reasons']['matched_skills']);
        $this->assertNotEmpty($result['reasons']['missing_skills']);
    }

    public function test_matches_csharp_cpp_dotnet_and_two_letter_skills(): void
    {
        $user = new User(['id' => '01HQTESTUSER000000000000004']);

        $fact = new CareerFact([
            'assertion_approved' => 'Senior Developer with strong background in C#, C++, .NET Core, Go and QA automation.',
            'status' => CareerFact::STATUS_CONFIRMED,
        ]);

        $trustedMock = Mockery::mock(TrustedCareerQuery::class);
        $trustedMock->shouldReceive('forMatching')
            ->once()
            ->with($user)
            ->andReturn(['facts' => [$fact], 'claims' => []]);

        $scorer = new FastFitScorer($trustedMock);

        $result = $scorer->score(
            $user,
            'Senior C# / C++ Software Engineer',
            'Developing game engines with C++, C#, and .NET services.'
        );

        $this->assertGreaterThanOrEqual(70, $result['score']);
        $this->assertSame(DiscoveredVacancy::FIT_HIGH, $result['level']);
        $this->assertTrue($result['reasons']['title_match']);
        $this->assertContains('C#', $result['reasons']['matched_skills']);
        $this->assertContains('C++', $result['reasons']['matched_skills']);
        $this->assertContains('.NET', $result['reasons']['matched_skills']);
    }

    public function test_memoizes_candidate_facts_and_keywords_per_user(): void
    {
        $user = new User(['id' => '01HQTESTUSER000000000000005']);

        $fact = new CareerFact([
            'assertion_approved' => 'Staff Go Engineer proficient in Docker and Kubernetes.',
            'status' => CareerFact::STATUS_CONFIRMED,
        ]);

        $trustedMock = Mockery::mock(TrustedCareerQuery::class);
        // Ensure forMatching is invoked EXACTLY ONCE despite multiple score() calls
        $trustedMock->shouldReceive('forMatching')
            ->once()
            ->with($user)
            ->andReturn(['facts' => [$fact], 'claims' => []]);

        $scorer = new FastFitScorer($trustedMock);

        $res1 = $scorer->score($user, 'Staff Go Engineer', 'Cloud infrastructure with Docker');
        $res2 = $scorer->score($user, 'Senior Go Developer', 'Microservices with Kubernetes');
        $res3 = $scorer->score($user, 'Lead DevOps Engineer', 'Docker and Kubernetes clusters');

        $this->assertTrue($res1['reasons']['title_match']);
        $this->assertTrue($res2['reasons']['title_match']);
        $this->assertSame(DiscoveredVacancy::FIT_HIGH, $res1['level']);
    }
}
