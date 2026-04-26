# POS — User Manual

Practical, click-by-click guide to using the Point of Sale system.

This manual is written for the people who use the system — Administrator,
Frontdesk cashier, and Owner / Big Boss. For the technical reference
(database schema, code paths, flows), see `pos-module.md`.

---

## Table of Contents

1. [Who does what](#1-who-does-what)
2. [Administrator — Managing the catalog](#2-administrator--managing-the-catalog)
3. [Frontdesk — Daily operations](#3-frontdesk--daily-operations)
4. [Owner / Big Boss — Reading the reports](#4-owner--big-boss--reading-the-reports)
5. [Common scenarios](#5-common-scenarios)
6. [Frequently asked questions](#6-frequently-asked-questions)
7. [Glossary](#7-glossary)

---

## 1. Who does what

| Role | Responsibilities |
|---|---|
| **Administrator** | Set up categories, menu items, prices, initial stock levels |
| **Frontdesk** (cashier) | Receive deliveries, sell at the register, void mistakes, end shift |
| **Owner / Big Boss** | Read POS sales and inventory movement reports |
| **Supervisor** | Same reports as the Owner |

Each person logs in with their own account. The system shows different
menus depending on the role.

---

## 2. Administrator — Managing the catalog

### 2.1 Open the management page

1. Log in as **administrator**
2. In the left sidebar, click **Frontdesk Kitchen** (icon: shopping cart)
3. You arrive at the unified catalog page

### 2.2 Add a category

A category groups menu items (for example, "Drinks", "Snacks",
"Toiletries"). Categories are how cashiers filter the menu at the
register.

1. From the Frontdesk Kitchen page, click the blue **Manage Categories**
   button at the top
2. Click **+ Add** (top-right of the category list)
3. Type the category name
4. Click **Save**

### 2.3 Add a menu item

1. From the Frontdesk Kitchen page, click the category you want to add
   the item under (it highlights in blue on the left)
2. Click the gray **+ Add Menu** placeholder card in the items area
3. Fill in:
   - **Item Code** — short code or barcode (required)
   - **Name** — display name shown to the cashier (required)
   - **Price** — peso amount, numbers only (required)
   - **Image** — optional photo (JPG or PNG, max 25MB)
4. Click **Save**

The new item appears immediately in the items grid.

> ⚠️ A new item starts with **0 stock** and shows as "Unavailable" on
> the register until you set its initial stock (see 2.5).

### 2.4 Edit a menu item

1. Click the item card you want to change
2. Update item code, name, price, or image
3. Click **Update**

> 💡 Every price change is automatically recorded in the price history
> log with the date, the old price, and the new price.

### 2.5 Set initial stock for an item

1. From the Frontdesk Kitchen page, with the right category selected,
   click the **Manage Inventory** button
2. You see all items in the category with their current stock levels
3. Click **Add Stock** next to the item
4. Type the quantity you want to add
5. Click **Save**

> 💡 For ongoing deliveries (receiving from a supplier), the **Frontdesk
> Stock-In** form is the right tool — it creates a delivery record. Use
> Manage Inventory only for setting starting levels or correcting counts.

### 2.6 Delete a menu item

> ⚠️ Deleting an item removes it permanently. Past sales records that
> reference the item still keep the item name and price (frozen at the
> time of sale), so old receipts and reports remain readable.

To delete: click the item, find the **Delete** action in the edit modal.

---

## 3. Frontdesk — Daily operations

### 3.1 Start your shift

1. Log in as a **frontdesk** user
2. The system prompts you to start a shift and assign a cash drawer
3. Once started, the POS becomes available

### 3.2 Open the POS

In the left sidebar, click **POS** (or navigate to the Point of Sale
page from the dashboard).

The screen has two halves:
- **Left:** menu tile grid, filterable by category and search
- **Right:** the cart panel

### 3.3 Receive a delivery (Stock-In)

When a supplier drops off goods, record them so stock is correct:

1. From the POS page, click the green **Stock In** button at the top
2. Pick the item from the dropdown
3. Type the **quantity received**
4. Optionally, add a note (supplier name, PO number)
5. Click **Record Stock In**

A green "Stock recorded" toast confirms it. The item's available stock
goes up by the amount you entered.

### 3.4 Make a cash sale (walk-in customer)

1. On the POS page, click menu tiles to add items to the cart
2. Use the +/− buttons in the cart to adjust quantities
3. Make sure the **"Charge to a room?"** toggle is **OFF** (default)
4. Click the blue **Place Order** button at the bottom of the cart
5. The Confirm Order modal opens, showing items, total, and a "Cash Sale"
   section
6. Type the **Cash Tendered** (the amount the customer handed you)
7. The **Change Due** appears automatically
8. Click **Confirm Order**
9. The Receipt modal opens — click **Print** to print on any printer
   (works with thermal printers too)
10. Click **Close** when done; the cart is empty and ready for the next
    customer

> 💡 The Confirm Order button is **disabled** until the cash tendered
> is at least the total. The screen will tell you "Short by ₱X" so you
> know how much more to ask for.

### 3.5 Make a room-charge sale (charge to a hotel guest)

For a guest who wants the items added to their room bill:

1. Add items to the cart as usual
2. Toggle **"Charge to a room?"** ON
3. In the search box, type the **room number** OR the **guest name**
4. From the dropdown, click the matching guest (the dropdown shows
   guest name, room number, floor, and bed type to help you confirm)
5. Once selected, the guest preview shows above the cart
6. Click the **Charge to Room** button
7. Confirm modal opens with a blue "Room Charge" banner showing the
   room number and guest name
8. Click **Confirm Room Charge**
9. The Receipt modal opens with "ROOM CHARGE — RM X — Guest Name" and
   "Will be settled at guest checkout."
10. Print or close

> 💡 No cash is collected for room charges. The amount is added to the
> guest's folio and they pay at hotel checkout.

### 3.6 Void a mistake (same shift only)

If you ring up the wrong order:

1. Click the dark **View Purchase History** button at the top of the POS
2. Find the order in the list (orders are sorted newest first)
3. Click the red **× Void** button next to the order
4. Confirm the dialog ("Yes, void")
5. The row turns grey with a strikethrough and a red **VOIDED** pill;
   the stock is automatically restored

> ⚠️ You can only void:
> - Your own orders (rung by you)
> - Orders from your current shift
> - Orders that aren't already voided
>
> If any of those fail, you'll see a clear message explaining why.

### 3.7 Reprint or review a receipt

The Purchase History modal shows every order from your shift, including
items, payment type, time, and total. Print the entire shift list with
the **Print** button at the top of the modal.

> ℹ️ Receipt reprint per-order is not available yet. To get the receipt
> after the fact, void and re-ring the order, OR print the Purchase
> History row.

### 3.8 End your shift

1. From the dashboard or sidebar, open **Cash on Hand**
2. The system shows your shift totals (sales, expenses, remittances,
   POS cash)
3. Type the actual cash you have in the drawer
4. Click **Confirm**, enter your **passcode**, and confirm again
5. The shift closes and you're logged out

The POS cash total saved with your shift includes both new POS sales
AND any older legacy sales from before the rebuild — nothing is lost.

---

## 4. Owner / Big Boss — Reading the reports

### 4.1 Open the report

1. Log in as **back office** (or **supervisor**, or **frontdesk** —
   any of these can see the report)
2. Open **Report Hub** from the sidebar
3. From the report dropdown, pick **Big Boss POS Report**

### 4.2 Choose a shift

The report needs a closed shift to display anything.

1. Use the **Select Shift** dropdown at the top
2. The list shows every closed shift this week, grouped by date and
   shift type (AM / PM), with the cashiers who worked it

If multiple cashiers worked the same shift (for example, two
frontdesks on AM), they appear together as one row in the dropdown.

### 4.3 Read the POS Sales section

This section shows every order rung during the shift session.

| Column | What it means |
|---|---|
| **Time** | When the order was placed |
| **Order** | Order number (format `OR-00045`) |
| **Cashier** | Who rang the sale |
| **Payment** | CASH (gray badge) or ROOM (blue badge with the room number and guest name) |
| **Items** | What was sold and how many |
| **Subtotal** | Price before discount |
| **Total** | Price after discount |

Voided orders appear with grey strikethrough and a red **VOIDED** pill.
They are listed for transparency but **not counted** in the totals.

The footer of the table shows three running totals:
- **Cash Sales (non-voided)** — what came into the drawer
- **Room-Charge Sales (non-voided)** — added to guests' folios
- **Gross POS** — both combined

### 4.4 Read the Inventory Movement section

For every item that moved during the shift:

| Column | What it means |
|---|---|
| **Source** | Frontdesk / Kitchen / Pub |
| **Item** | The menu item |
| **Opening** | Stock at the start of the shift |
| **In** | Stock received (deliveries) plus voided sale restorations |
| **Out** | Stock sold |
| **Closing** | Stock at the end of the shift |

Items with no movement during the shift are not shown — keeps the
report focused on what actually changed.

### 4.5 Print or export

Two buttons at the top of the report:

- **Print Report** — opens the browser print dialog, hides everything
  except the report
- **Export HTML** — downloads a self-contained HTML file you can save,
  email, or open later

---

## 5. Common scenarios

### 5.1 "I rang up the wrong item or wrong quantity"

If you haven't confirmed yet → click the **× Void** button on the cart
line, OR click **Clear** to empty the entire cart.

If you already confirmed → open **View Purchase History**, find the
order, click **Void**. Stock is restored automatically.

### 5.2 "Customer pays cash with a big bill"

Type the actual amount they handed over in **Cash Tendered**. The
**Change Due** updates live. For example: total is ₱150, customer
hands over ₱500 → type 500 → Change Due shows ₱350.

### 5.3 "Customer is a hotel guest — wants to charge to room"

Toggle **Charge to a room?** ON, search by room number or guest name,
select the guest, then **Charge to Room**. No cash is collected.

### 5.4 "Item shows Unavailable but I'm sure we have stock"

Two possibilities:
- The item has no inventory record yet (admin must set initial stock —
  see 2.5)
- All stock has been sold during the shift (record a delivery via
  **Stock In** if more arrived)

### 5.5 "Where do I see how much cash I've collected today?"

In the POS page, click **View Purchase History**. The table shows
every order with its amount; the bottom row shows
**TOTAL (CASH, NON-VOIDED)** for the shift.

### 5.6 "Can a different cashier void my order?"

No. Only the cashier who rang the order can void it, and only during
the same shift. If the cashier has gone home, an admin / supervisor can
manually adjust through the back office, but in normal operation the
order stays as-is.

### 5.7 "How do I add a discount?"

The Discount field is currently hidden — there's no per-sale discount
in normal operation. If your business needs to offer ad-hoc discounts,
ask the developer to enable the discount input.

---

## 6. Frequently asked questions

**Q: What happens to a sale if the printer fails?**
A: The sale is already saved in the database before the receipt opens.
Click **Close** on the receipt modal — you can find the order in
**View Purchase History** and reprint the row from there.

**Q: What if the page reloads while I have items in the cart?**
A: The cart is not saved — it lives only in your browser. After a
reload, you'll need to re-add the items. Avoid hitting Refresh during
a sale.

**Q: Can two cashiers use the same drawer at the same time?**
A: No. Each shift assigns one drawer to one cashier. If two cashiers
need to work the same drawer, they take turns (one ends shift, the
other starts).

**Q: A customer wants a refund — what do I do?**
A: If the order was rung in your current shift, void it via Purchase
History — this restores stock and gives the customer their cash back.
If the order is from an earlier shift, escalate to a supervisor.

**Q: The "Place Order" button says "Add items to place order" and
won't let me click.**
A: That just means your cart is empty. Click some menu tiles first.

**Q: I see "Drawer 1" everywhere on the POS page — is that a problem?**
A: No, it's the same drawer name shown in different places (top header
is your account info, the rest is page info). It's just a label.

**Q: My discount entry was rejected with "Discount cannot exceed
subtotal."**
A: You typed a discount larger than the cart total. Lower the discount
amount. (Note: the discount input is hidden by default in the current
release.)

**Q: Why does the receipt always show CHANGE even when I gave exact
cash (₱0 change)?**
A: A real receipt always prints the change line — it's proof to the
customer that no change was withheld. Don't worry about it.

**Q: How do I print on a thermal printer?**
A: Connect the thermal printer to your computer like any other
printer. Click **Print** on the receipt modal. In the browser's print
dialog, pick the thermal printer. The receipt is designed to render
correctly on both standard A4 and 58mm/80mm thermal rolls — no driver
configuration needed.

---

## 7. Glossary

**Cart** — the list of items you've added but not yet confirmed. It's
in-memory only; refreshing the page clears it.

**Cash drawer** — your assigned till for the shift. POS won't open
without one assigned.

**Cash Tendered** — the amount of cash the customer hands you. You
type this in; the system computes the change.

**Change Due** — the cash you give back to the customer (Cash Tendered
minus Total).

**Folio** — a guest's running bill at the hotel. Room-charge POS sales
go on the folio and are paid at hotel checkout.

**Order** — one customer interaction at the POS. One order can have
many items.

**Order ID** — a number like `OR-00045` printed on the receipt and
shown in Purchase History. Use it to reference a specific sale.

**Place Order** — the action of submitting a sale (after reviewing the
cart). Same as "ring up" or "complete sale".

**Print Receipt** — uses the browser's built-in print dialog. Works
with any printer the computer has installed (A4, Letter, thermal).

**Receipt** — the printed slip given to the customer. Always shows the
items, total, payment type, and (for cash) the change.

**Room Charge** — a sale charged to a checked-in guest's room
instead of paid in cash. Settled at guest checkout.

**Shift** — your working session. Starts when you log in and assign
a drawer; ends when you close out via Cash on Hand.

**Stock In** — recording a delivery from a supplier. Adds stock and
creates an audit row.

**Void** — reversing a sale. Restores the stock, marks the order as
cancelled, and excludes it from cash totals. Same shift, same cashier
only.

**VOIDED** — the red label that appears on voided orders in Purchase
History and on a re-printed receipt.

---

*For developer-facing technical details (architecture, schema, code
paths), see `pos-module.md` in this folder.*
