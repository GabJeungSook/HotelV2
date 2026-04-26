# Kiosk Room Sequence — Your Sketch vs. What's Built

This is a side-by-side reading of the two diagrams you sent us, mapped onto the
kiosk room sequence we shipped on `future-updates`. The intent is so you can walk
into testing already knowing where each idea lives in the system, instead of
discovering it cold.

**Source diagrams reviewed:**
- *Screenshot A* — your "Sequential room rotation and queuing (round-robin)" diagram
- *Screenshot B* — your hand-drawn paper sketch (two floors of rooms, circled pool, `Booked online`, `check in / clean`)

Both diagrams describe the same model. As far as we can tell from the drawings,
**the sequence we built is the sequence you drew.** The table below is the proof,
plus the few spots that need a yes/no from you once you've used it.

---

## 1. What you drew vs. what we built

| Your idea (from the diagrams) | What's built | Where you can see it live |
|---|---|---|
| A pool of all rooms, with occupied ones X'd out / removed from the pool | Excluded automatically before each pick. Occupied, in-cleaning, held, and reserved rooms are filtered out of the pool. | Any room you don't see on the kiosk — that's what's been excluded |
| Queue / waiting stack feeding the displayed rooms | A "waiting stack" exists per branch + room type. It feeds the displayed batch automatically. | Frontdesk → **Room Monitoring** → click **Kiosk Batch** → see the **NEXT** and **AFTER** rows |
| One displayed room per floor at a time | Built exactly this way — the kiosk shows at most one room per floor per type. | Open the kiosk → pick a type → you'll see at most one card per floor |
| Rooms cycle in batches (Screenshot A's small "next" box at the bottom) | "Throw next batch" runs automatically when the current batch is fully used and confirmed. | Watch the **NOW** row in the Kiosk Batch modal change after a full batch is consumed |
| `check in / clean` excluded while a room is being cleaned | Rooms in cleaning are filtered out. When the room boy finishes cleaning, the room rejoins the rotation automatically — including filling a blank floor mid-batch. | Mark a room "cleaning" → it disappears from the kiosk; finish cleaning → it comes back |
| `Booked online` excluded from the rotation | **Today**: any reservation in the frontdesk reservation queue is excluded. There is no separate "online channel" feeding the system right now — if you have a separate online booking source in mind, flag it (see §3). | Reserve a room from the frontdesk → it disappears from the kiosk |
| Rooms returning to the rotation when something is cancelled | If a guest cancels at frontdesk OR doesn't show up within the time limit (default 10 minutes), the room returns to the same batch — not the back of the queue. | Reserve a room from kiosk → cancel from frontdesk → that exact room reappears on the kiosk |
| The numbering order — lowest first within a floor | The picker uses **never-used rooms first**, then **least-recently-used**, then natural numeric order ("3" before "5A" before "21"). This balances "use unused rooms" with "ascending number" — same goal as the sketch but spreads usage so the same low-numbered room isn't picked over and over. | Watch which room gets picked on each throw — it'll prefer rooms that have never been used yet |

---

## 2. Things that exist but are invisible until you open the right screen

These are real and working, but you won't find them without being shown:

- **The "Kiosk Batch" viewer.** Frontdesk → Room Monitoring → top-right button. This is the single best place to see the rotation working. It shows:
  - **NOW** — what's on the kiosk right now (green = pickable, amber = picked but waiting on frontdesk to confirm)
  - **NEXT** — exactly which rooms will appear when the current batch finishes
  - **AFTER** — the batch after that
- **Per-room-type independence.** Single, Double, and Twin rotate as separate sequences. Picking a Double doesn't disturb Single or Twin batches.
- **Auto-recovery.** If a room is taken out of the rotation for any reason (manual frontdesk check-in, status edit, etc.), the kiosk self-heals on its next render — no admin action needed.

---

## 3. Things we want a yes/no from you on, once you've tested

We're not building any of these yet. They're written down here so you can react to
them while you're using the system, instead of trying to imagine them up front.

1. **Reason labels in the Kiosk Batch viewer.** Right now the viewer shows *which* rooms are excluded (by their absence). It does **not** label *why* — e.g. "RM 23 — booked", "RM 24 — being cleaned." Your sketch labels rooms with their reason. Do you want us to add those labels to the viewer?
2. **A separate online-booking channel.** Today, the system treats anything in the frontdesk reservation queue as "held". If `Booked online` in your sketch means an online portal that isn't yet connected to the system, tell us — that's separate work.
3. **A "next up" hint on the kiosk screen itself.** The kiosk currently shows only the active batch. The "next up" preview lives on the frontdesk side. Do you want guests to see a small "coming next" hint on the kiosk too?
4. **Manual override.** Should a frontdesk staff member be able to force a specific room into the displayed batch (or pull one out), or do you want the rotation to stay fully automatic?

---

## 4. Suggested order to test

Following this sequence is the fastest way to convince yourself the rotation
behaves the way the sketch does.

1. Open the kiosk → pick a type → note the rooms shown (one per floor).
2. Open the **Kiosk Batch** viewer on the frontdesk side. Confirm NOW matches what you saw on the kiosk.
3. Note the rooms in NEXT and AFTER — these are the queue.
4. Have one guest go through a kiosk check-in. Reopen the viewer. The picked room should be amber-struck-through in NOW.
5. Cancel that reservation from frontdesk. Reopen the viewer. The room should be back to green in NOW.
6. Pick all rooms in NOW (or have multiple guests do it) and have frontdesk confirm them. The viewer's NOW row should refresh and now match what was previously NEXT.
7. Mark a room "cleaning" in the rotation, then finish cleaning. The room should rejoin the rotation automatically.

If anything in this list doesn't match what you expect, that's the conversation we
want to have.
