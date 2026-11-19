# How to manage user access

Create and review ePT accounts with the access needed for their assigned work.

## Before you start

Obtain the account owner's name, approved responsibilities and permitted schemes or participants.
Use an administrator with the required configuration or participant-management privileges.
Keep participant records separate from the accounts that enter their results.

## Add a PT manager

1. Open **Configure → PT Managers**.
2. Click **Add New PT Manager**.
3. Enter the person's name, primary email, contact information and initial password.
4. Select the active schemes the person can manage.
5. Select only the approved privileges.
6. Set the intended account status and submit the form.
7. Arrange secure delivery of the initial credentials to the account owner.
8. Confirm the owner can sign in and complete the required password change.

The administrator form uses scheme assignments and individual privileges.
Do not assume that a generic role label defines the effective access.
See the [privilege reference](AdminModuleGuide.md#privilege-system).

## Assign participant-facing access

1. Open **Configure → Data Manager (Participant Login)**.
2. Find the account, or click **Add New Data Manager (Participant Login)** to create one.
3. Open **Configure → Participant Login Map**.
4. Choose the data manager.
5. Click each participant the account enters results for, so it moves from **Not mapped** to **Mapped**.
6. Click **Save changes**.
7. Confirm the participant is enrolled in the relevant scheme and shipment.
8. Sign in as the data manager, or click **View as** on the data manager list. **View as** appears only for admins with the view-as-participant privilege. Confirm the account sees the intended shipment and participant.
9. Confirm an unrelated participant does not appear.

Use [participant management training](training/part1-setup-and-participants.md) for the setup sequence.

## Change or remove access

1. Record the approved change in the local access record.
2. Edit the account's status, scheme access, privileges or participant mappings.
3. Ask the owner to sign out and sign in again when testing a privilege change.
4. Verify the revised access, including rejection of a disallowed operation.
5. For deactivation, verify that a fresh login is rejected.
6. If the account uses API clients, include token and client access in the verification.

Do not delete participant history merely to remove a person's login access.
Do not assume that changing one web-account field invalidates every existing client session.

## Recover account access

1. Verify the requester's identity through the local support procedure.
2. Use the password-recovery flow or the authorized [password reset tools](cli-tools.md#password-reset).
3. Confirm the owner can authenticate with the replacement credentials.
4. Record the completed support action without recording the password.

## Review access periodically

1. Compare active accounts with the current staff and responsibility list.
2. Review administrative privileges, active schemes, participant mappings and coordinator access.
3. Remove access no longer approved by the programme owner.
4. Record the reviewer, date, changes and verification results.
