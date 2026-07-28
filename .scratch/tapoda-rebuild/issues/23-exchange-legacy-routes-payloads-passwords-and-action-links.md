# Exchange legacy routes, payloads, passwords, and action links

Status: `ready-for-agent`

## What to build

Preserve approved legacy entry points by translating them into canonical commands and safe outcomes. Exchange active tokens once, map old payloads and numeric identifiers, verify and rehash supported passwords, retain historical reads, and measure every adapter for later removal.

## Acceptance criteria

- [ ] Approved application, acceptance, cancellation, confirmation, result, document, and bookmarked routes resolve to explicit adapters or redirects.
- [ ] Legacy encrypted tokens are decrypted only inside the restricted exchange, validated for action/ownership/state, replaced by a hashed one-use token, and never reused.
- [ ] Legacy save payloads map to server-resolved applications, semantic keys, typed commands, and owning-module authorization; clients cannot set status or privileged persona context.
- [ ] Supported password hashes verify and rehash; unsupported hashes enter safe recovery.
- [ ] Generic messages use typed outcome codes and approved copy; arbitrary HTML and callbacks are rejected.
- [ ] Historical applications remain readable from immutable or compatibility projections.
- [ ] Every adapter emits telemetry, comparison results, owner, expiry, and removal signals without logging secrets or personal data.
- [ ] `FLOW-APP-06`, `FLOW-INV-01`, `FLOW-MSG-01`, active-link, replay, ownership-attack, and exact redirect/result fixtures pass.

## Blocked by

- [04 Approve compatibility and coexistence contracts](./04-approve-compatibility-and-coexistence-contracts.md)
- [11 Register, verify, sign in, recover, and secure an account](./11-register-verify-sign-in-recover-and-secure-an-account.md)
- [13 Submit an initial new-person application](./13-submit-an-initial-new-person-application.md)
- [18 Invite and confirm one selected applicant](./18-invite-and-confirm-one-selected-applicant.md)
- [19 Complete alumni/staff responses, decline, cancel, and withdrawal](./19-complete-alumni-staff-response-decline-cancel-and-withdrawal.md)
