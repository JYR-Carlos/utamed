# General Agent Guidelines - UTAMED

## Mandatory Trello Synchronization

Before addressing any development request, bug fix, refactor, or visual adjustment:

1. **Proactively Consult the Active Board**:
   - Inspect the active Trello board (`arreglos-reunion-23-09-2026` or the board configured in the Trello MCP server).
   - Check if the user request matches an existing card in any of the lists (`Por definir`, `Pendiente`, `En progreso`, `En revisión`, `Hecho`).
   - Cite the card (code, title, Trello link) and base the solution on its description and Acceptance Criteria (DoD).
   - When starting active coding, move the card to `En progreso`.

2. **Automatic Member Assignment (Self-Assign Prompt Runner)**:
   - When taking or creating a card for execution, automatically assign the team member who ran the prompt.
   - Detect identity using the local git configuration (`git config user.name` / `git config user.email`) and match against the board members via `get_board_members`.
   - **Interactive Fallback**: If the member identity cannot be determined with certainty, interactively ask the user to select their member profile using `ask_question`.
   - Once resolved, assign the member to the card using `assign_member_to_card`.

3. **Strict State Conventions**:
   - **`En revisión` = Human / Technical Review**:
     - Indicates that the agent finished implementation and automatic verifications.
     - Mandatory: Move to `En revisión`, mark completed DoD items (`[x]`), and post a comment summarizing changes, commits, and modified files.
   - **`Hecho` (Done) = Approved by User**:
     - **Strict Rule**: The agent **NEVER** moves a card to `Hecho` autonomously.
     - Only transition to `Hecho` when the user explicitly provides approval (*"approved"*, *"done"*, *"validated"*, *"move to done"*).
   - **`Pendiente`**: Backlog of refined tasks ready to be taken.
   - **`Por definir (preguntar al director)`**: Requirements with external dependencies or pending institutional definitions.

4. **New Requirements**:
   - If no card exists for the request, alert the user and coordinate creating it on the board following the standard format with Fibonacci estimation and Acceptance Criteria (DoD), self-assigning the user.
