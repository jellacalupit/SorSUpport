Temporary note (dev): Auth test failures exist after disabling email verification.

Next action options:
1) Skip failing Auth/EmailVerification tests in phpunit config or mark them skipped.
2) Or adjust auth behavior to re-align with existing tests.

Current failing tests (observed):
- Tests\Feature\Auth\AuthenticationTest
- Tests\Feature\Auth\Module2AuthenticationFlowTest
- Tests\Feature\Auth\RegistrationTest
- Tests\Feature\Auth\EmailVerificationTest

Email verification screen render test already passes.

