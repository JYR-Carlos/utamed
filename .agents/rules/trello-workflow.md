# Mandatory Trello Synchronization Rule

## 1. Mandatory Proactive Board Consultation
Before implementing any change, bug fix, visual tweak, refactor, or new feature requested by the user:
* **Consult the active Trello board** (`arreglos-reunion-23-09-2026` or the one configured in the Trello MCP server).
* Verify whether the user's request matches an existing card (or set of cards) by searching keywords, identifier code (`[BUG-01]`, `[FEAT-02]`, `SEC-01`, etc.), route, component, or technical description across lists (`Por definir`, `Pendiente`, `En progreso`, `En revisión`, `Hecho`).
* If a related card exists:
  - Explicitly reference it in responses and plans (code, title, and Trello URL).
  - Strictly follow and satisfy its description and Acceptance Criteria (DoD).
  - When starting active development in code, move the card to **`En progreso`**.

## 2. Automatic Member Assignment (Self-Assign Prompt Runner)
When taking a card into progress or creating a new card:
* Auto-assign the person who executed the prompt to the card.
* **Identity Detection**:
  1. Inspect local git author settings via `git config user.name` and `git config user.email`.
  2. Query board members with `get_board_members` from the Trello MCP tool.
  3. Match name/username/email to the board members list.
* **Interactive Fallback**:
  - If the member cannot be identified unambiguously, ask the user interactively (via `ask_question`) to choose their profile from the active board members.
* **Assignment Execution**:
  - Call `assign_member_to_card` with the target `cardId` and matched `memberId`.

## 3. Strict State Definitions and Transitions

### `En revisión` = Human / Technical Review
* **Meaning**: The feature, fix, or refactor was implemented and verified technically by the agent. The task is ready for human inspection, testing, and code review.
* **Mandatory actions when moving to `En revisión`**:
  1. Move the card to list **`En revisión`**.
  2. Check completed items (`[x]`) in the *Acceptance Criteria (DoD)* checklist.
  3. Add a comment to the card summarizing changes, modified files, commits, and branch name.

### `Hecho` (Done) = Approved by User
* **Meaning**: The card has been formally **approved and validated by the user**.
* **Strict Rule**: The agent **NEVER** moves a card to `Hecho` autonomously.
* **Transition Condition**: Only transition to `Hecho` when the user explicitly expresses approval (e.g., *"approved"*, *"ready"*, *"looks good"*, *"move to done"*).

### `Pendiente`
* Refined and prioritized cards in the queue, ready to be picked up for development.

### `Por definir (preguntar al director)`
* Cards whose scope, business rules, or causes are incomplete or awaiting institutional / director decisions.

## 4. New Requirements or Untracked Bugs
If the user's request does not match any card on the board:
* Notify the user that no existing card was found.
* Create or suggest creating the card in the appropriate list (`Pendiente` or `Por definir`), applying the project's standard format with Fibonacci estimation, Acceptance Criteria (DoD), and self-assigning the user.
