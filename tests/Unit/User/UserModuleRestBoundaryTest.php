<?php

declare(strict_types=1);

namespace Tests\Unit\User;

use App\Http\Middleware\ValidateUserApiRequest;
use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

final class UserModuleRestBoundaryTest extends TestCase
{
    public function test_every_teammate_endpoint_consumed_by_the_user_module_uses_the_api_stack(): void
    {
        foreach ([
            'api.elections.summaries',
            'api.elections.voter-catalog',
            'api.elections.organizer',
            'api.elections.admin-overview',
            'api.elections.availability',
            'api.elections.candidates',
            'api.candidates.metrics',
            'api.votes.status',
        ] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            self::assertNotNull($route, "Missing REST route [{$routeName}].");
            self::assertContains('api', $route->gatherMiddleware(), "Route [{$routeName}] must use the API middleware stack.");
            self::assertNotContains('web', $route->gatherMiddleware(), "Route [{$routeName}] must not require browser/CSRF middleware.");
        }
    }

    public function test_every_user_voter_function_consumed_by_teammates_is_exposed_through_rest(): void
    {
        foreach ([
            'api.voters.eligibility',
            'api.voters.private-enrollments',
            'api.voters.private-directory',
            'api.voters.private-enrollments.selection',
        ] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            self::assertNotNull($route, "Missing User/Voter REST route [{$routeName}].");
            self::assertContains('api', $route->gatherMiddleware(), "Route [{$routeName}] must use the API middleware stack.");
            self::assertContains(ValidateUserApiRequest::class, $route->gatherMiddleware(), "Route [{$routeName}] must authenticate service requests.");
            self::assertNotContains('web', $route->gatherMiddleware(), "Route [{$routeName}] must not require browser/CSRF middleware.");
        }
    }

    public function test_user_owned_services_do_not_import_teammate_models_or_application_services(): void
    {
        $forbiddenDependencies = [
            '/App\\\\Models\\\\(?:Election|Candidacy|Vote|Payment)\\b/',
            '/App\\\\Services\\\\ElectionService\\b/',
            '/App\\\\Services\\\\CandidateRegistration\\\\/',
            '/App\\\\Services\\\\Voting\\\\/',
            '/App\\\\Services\\\\Payment\\\\/',
            '/App\\\\Contracts\\\\(?:VotingIntegration|VoteCastingService|RegistrationService|PaymentGateway(?:Contract)?)\\b/',
            '/(?:DB::table|->(?:from|join|leftJoin|rightJoin))\s*\(\s*[\'\"](?:election|candidacy|vote|payment)[\'\"]/',
        ];
        $checkedFiles = 0;
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), RecursiveDirectoryIterator::SKIP_DOTS));
        $explicitlyOwnedFiles = [
            realpath(app_path('Models/User.php')),
            realpath(app_path('Models/VoterEnrollment.php')),
        ];

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            $isExplicitlyOwned = in_array(realpath($file->getPathname()), $explicitlyOwnedFiles, true);
            $hasUserOwnershipHeader = is_string($contents)
                && str_contains(substr($contents, 0, 320), 'PIC: Chew Zhexuan');

            if (! is_string($contents) || (! $isExplicitlyOwned && ! $hasUserOwnershipHeader)) {
                continue;
            }

            $checkedFiles++;
            foreach ($forbiddenDependencies as $dependencyPattern) {
                self::assertDoesNotMatchRegularExpression(
                    $dependencyPattern,
                    $contents,
                    "User/Voter module file [{$file->getPathname()}] bypasses its REST boundary.",
                );
            }
        }

        self::assertGreaterThan(10, $checkedFiles, 'The User/Voter ownership scan did not inspect the expected module files.');
    }
}
