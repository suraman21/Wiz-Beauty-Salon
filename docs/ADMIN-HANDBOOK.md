# The salon's handbook

For the people who run the salon, not for developers. Everything here is done in a browser at `https://wiz.arkeonethiopia.com/admin`. Nothing here needs a phone call to a technician.

Sign in at `/admin/login` with the email and password you were given. Sessions last until you leave or are idle too long; the padlock in the address bar should always be there.

---

## The screens

The sidebar shows what your role allows.

| Screen | What it is for |
| --- | --- |
| **Bookings** | Every request and appointment. Confirm, decline, move, complete, mark a no-show |
| **Services** | The four things you offer and how they look on the site |
| **Gallery** | Photos of your work |
| **Website images** | The big pictures on the home, about and services pages |
| **Reviews** | Approve what appears on the site, or type in one a customer gave you in person |
| **Opening hours** | Your week, and days you are closed |
| **Messages** | Notes from the contact form |
| **Team** *(admin only)* | Add a colleague, change a password, switch someone off |
| **Activity** *(admin only)* | Who changed what, and when |
| **Email designs** *(admin only)* | The wording and look of the emails customers receive |
| **Settings** *(admin only)* | Salon details, booking rules, email sending |

---

## A booking's life

```
Customer requests  →  Requested  →  you phone them  →  Confirmed  →  they come  →  Completed
                                          ↘ Declined                                    ↘ No-show
Customer cancels through their link  →  Cancelled
```

**Requested** means nobody has spoken yet. It is not an appointment. The dashboard and the sidebar count these; clear them every day.

- **Confirm** — the customer gets an email saying the time is held, with a link to move or cancel it.
- **Decline** — the customer gets an email saying so, and the slot is released immediately, so someone else can have it.
- **Complete / No-show** — closes the record. Nothing is deleted, ever: the history is the salon's record of what happened.
- **Move** — you can move a booking even if the customer is inside the change window; the rule exists to protect your calendar from late customer changes, not to stop you from helping someone.

If the same time is requested twice, only the first is accepted. The second customer is told the time has just gone and is shown the free times again.

---

## The change window — the one setting worth understanding

**Settings → Booking rules.**

It decides how late a customer may move or cancel their own appointment without asking you. Set it to the notice *you* need:

| Window | Use it when |
| --- | --- |
| **No cut-off** | you can always absorb a late change |
| **12 hours** | same-day changes are fine before the morning |
| **24 hours** (default) | the rule the site shipped with |
| **48–72 hours** | bridal mornings, or anything that needs stock or a second pair of hands |

Whatever you choose, the website, the manage page, the confirmation email and the booking terms start saying it immediately — you do not need to ask anyone to update the text. A customer who is too late sees your reason and your phone number instead of a button that would not work.

The same screen holds three more rules: **how soon** a slot may be booked (default 2 hours ahead), **how far ahead** (default 60 days) and **how long a slot is** (default 30 minutes).

---

## Opening hours and closures

**Opening hours** is your normal week: opening time, closing time, or closed.

**Closures** are exceptions: a holiday, a wedding party, a day the salon is being painted. Pick the first and last day, give a reason — the reason is what customers see ("Closed: Public holiday"). A closure takes those days off the booking calendar immediately.

Booking a closure does **not** move appointments that already exist on those days. Open **Bookings** and move them yourself; the customer is emailed either way.

---

## Services and pictures

**Services** is what a customer can book: name, Amharic name, a short description and a picture. Order matters — that is the order on the site. Hiding a service removes it from the website and from the booking page without deleting anything, so its old bookings still make sense.

**Gallery** is your work: upload a photo, write a caption, choose a category, and publish it. **Website images** is the handful of large pictures the design uses (the hero, the about page, the four gallery bands). Where you have not uploaded one, the site keeps the picture built into its design; where you have, yours replaces it.

Photos are shrunk and cleaned automatically on upload. JPG, PNG and WebP up to a few megabytes; anything else is refused with a message rather than a broken page. Use landscape photos where the design is wide, and check the home page afterwards on a phone.

---

## Reviews

Reviews appear on the site **only after you approve them**. Type in ones that came in person or on the phone — a review the customer can see, from someone who never opened the site, is still true. Deleting a review is permanent.

---

## Messages, team, activity

- **Messages** — everything sent through the contact form. Mark read, or delete once handled.
- **Team** — add a colleague with a password; a **staff** account can run the salon day to day; an **admin** account can also change settings, email designs and other people's accounts. Switching someone off ends their access immediately without deleting their history.
- **Activity** — a plain record: who confirmed what, who changed which setting, who signed in. If something looks wrong, start here.

---

## Emails

**Email designs** edits what customers receive: the subject, the wording, and the layout of the confirmation, cancellation and reschedule emails. Changes are drafts until you **publish**; every published version is kept, so a bad edit can be put back.

**Settings → Email** holds the sending account and a **Send a test** button. Use it after any change: if a test does not arrive, the confirmation emails are not arriving either.

Two rules that email cannot break: the booking link and the change-window sentence are generated by the system, and the sentence always matches the rule in Settings even if the design around it is edited.

---

## If something is wrong

| What you see | What to do |
| --- | --- |
| A customer says the link in their email does not work | Send them to `/booking/recover`, or copy the link from the booking's page in the panel |
| A time is offered that you cannot work | Add a **Closure** for that day, or change **Opening hours**, then phone anyone already booked |
| The website shows an old photo or an old price | Wait one minute and reload — the site caches for up to a minute |
| A booking shows a name or number you do not recognise | It is a real request from the public form; phone it before declining |
| Two people say they booked the same time | Only one can hold a slot. Check **Activity** and confirm the one whose request arrived first; offer the other a nearby time |
| You cannot sign in | Wait 15 minutes — five wrong passwords locks the account briefly — then use a colleague's admin account to reset yours |
| The panel will not load at all | Phone whoever maintains it; `docs/RUNBOOK.md` is written for them |

Nothing in this panel deletes an appointment. If you need one gone from the day's list, cancel it — the record stays, and the customer is told.
