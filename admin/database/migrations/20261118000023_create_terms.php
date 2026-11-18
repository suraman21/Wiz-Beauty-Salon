<?php
declare(strict_types=1);

use Wiz\Core\Database;
use Wiz\Database\Migration;

/**
 * The booking terms become content, not code.
 *
 * Until now the document lived in `src/routes/booking_.information.tsx`: twelve
 * sections, thirty clauses, each written twice (English and Amharic). Changing a
 * sentence — the notice period for a bridal booking, the wording of a complaint
 * — needed a developer, a pull request and a deploy, which is not how a decision
 * about the salon's own rules should travel.
 *
 * Two tables, deliberately small:
 *
 *   terms_sections   the twelve headings, with their stable anchor and order
 *   terms_clauses    the numbered clauses inside each, in both languages
 *
 * `slug` is the anchor the contents list links to, so it is generated once and
 * never edited — a customer's bookmark to #window must keep working after the
 * heading is renamed.
 *
 * The seed is the document as published on 2026-11-17, with three placeholders
 * where the text must stay live:
 *
 *   {{window}}      "24 hours before the appointment" (or "any time before…")
 *   {{window_am}}   the same phrase in Amharic
 *   {{policy}}      the salon's own summary sentence
 *
 * They are resolved by ChangePolicy when the document is served, so the terms
 * cannot promise a window the booking service would refuse.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute(<<<'SQL'
            CREATE TABLE IF NOT EXISTS terms_sections (
                id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                slug        VARCHAR(60) NOT NULL,
                title_en    VARCHAR(160) NOT NULL,
                title_am    VARCHAR(160) NOT NULL,
                sort_order  INT NOT NULL DEFAULT 1,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY terms_sections_slug (slug),
                KEY terms_sections_order (sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $db->execute(<<<'SQL'
            CREATE TABLE IF NOT EXISTS terms_clauses (
                id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                section_id  BIGINT UNSIGNED NOT NULL,
                sort_order    INT NOT NULL DEFAULT 1,
                body_en     TEXT NOT NULL,
                body_am     TEXT NOT NULL,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY terms_clauses_section (section_id, sort_order),
                CONSTRAINT terms_clauses_section_fk
                    FOREIGN KEY (section_id) REFERENCES terms_sections (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        // Seed only into an empty table: re-running migrations must never
        // overwrite wording the salon has edited.
        if ((int) $db->scalar('SELECT COUNT(*) FROM terms_sections') > 0) {
            return;
        }

        $db->execute(
            'INSERT INTO terms_sections (slug, title_en, title_am, sort_order)
             VALUES (:slug1, :title_en1, :title_am1, 1)',
            [
                ':slug1' => 'about',
                ':title_en1' => 'About these terms',
                ':title_am1' => 'ስለዚህ ስምምነት',
            ],
        );

        $sectionId1 = (int) $db->lastInsertId();

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section1_1, 1, :body_en1_1, :body_am1_1)',
            [
                ':section1_1' => $sectionId1,
                ':body_en1_1' => 'These terms cover every appointment booked with WIZ Beauty Salon, whether you book on this website, by phone, on WhatsApp or in person at the salon.',
                ':body_am1_1' => 'ይህ ስምምነት በ WIZ የውበት ሳሎን የሚያዙትን ሁሉንም ቀጠሮዎች ይሸፍናል፤ በዚህ ድረ-ገጽ፣ በስልክ፣ በዋትስአፕ ወይም በሳሎኑ ላይ በአካል ቢያዙም።',
            ],
        );

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section2_1, 2, :body_en2_1, :body_am2_1)',
            [
                ':section2_1' => $sectionId1,
                ':body_en2_1' => 'Booking an appointment means you accept these terms. If anything here is unclear, ask us before you book — we would rather answer a question now than disappoint you later.',
                ':body_am2_1' => 'ቀጠሮ ማስያዝ ማለት ይህን ስምምነት መቀበልዎ ማለት ነው። አንድ ነገር ግልጽ ካልሆነ ከመያዝዎ በፊት ይጠይቁን — አሁን መመለስ ከበኋላ ማሳዘን ይሻላል።',
            ],
        );

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section3_1, 3, :body_en3_1, :body_am3_1)',
            [
                ':section3_1' => $sectionId1,
                ':body_en3_1' => 'We may update these terms as the salon changes. The version in force is the one published here on the day of your appointment.',
                ':body_am3_1' => 'ሳሎኑ ሲሻሻል ይህን ስምምነት ልናስተካክል እንችላለን። በሥራ ላይ ያለው ቅጂ በቀጠሮዎ ቀን በዚህ ገጽ ላይ የታተመው ነው።',
            ],
        );

        $db->execute(
            'INSERT INTO terms_sections (slug, title_en, title_am, sort_order)
             VALUES (:slug2, :title_en2, :title_am2, 2)',
            [
                ':slug2' => 'booking',
                ':title_en2' => 'Making a booking',
                ':title_am2' => 'ቀጠሮ ማስያዝ',
            ],
        );

        $sectionId2 = (int) $db->lastInsertId();

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section1_2, 1, :body_en1_2, :body_am1_2)',
            [
                ':section1_2' => $sectionId2,
                ':body_en1_2' => 'A booking made on this website is a request. It becomes an appointment only when the salon confirms it — by phone, by message, or by a confirmation email.',
                ':body_am1_2' => 'በዚህ ድረ-ገጽ የሚደረግ ቦታ ማስያዝ ጥያቄ ነው። ሳሎኑ በስልክ፣ በመልእክት ወይም በማረጋገጫ ኢሜል ሲያረጋግጥ ብቻ ቀጠሮ ይሆናል።',
            ],
        );

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section2_2, 2, :body_en2_2, :body_am2_2)',
            [
                ':section2_2' => $sectionId2,
                ':body_en2_2' => 'Every booking gets a reference number. It is the fastest way for us to find you, so keep it — it is printed in your confirmation email and on your manage link.',
                ':body_am2_2' => 'እያንዳንዱ ቀጠሮ የራሱ የማጣቀሻ ቁጥር አለው። እርስዎን ለማግኘት ፈጣኑ መንገድ ስለሆነ ይያዙት — በማረጋገጫ ኢሜልዎና በማስተዳደሪያ ሊንክዎ ላይ ተጽፎ ይገኛል።',
            ],
        );

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section3_2, 3, :body_en3_2, :body_am3_2)',
            [
                ':section3_2' => $sectionId2,
                ':body_en3_2' => 'The manage link we send you is private and expires 24 hours after it is sent. Anyone who has it can move or cancel the appointment, so please do not forward it. You can always ask for a fresh link from the Manage booking page — that link is the only credential, and we will not ask you for a password.',
                ':body_am3_2' => 'የምንልክልዎ የማስተዳደሪያ ሊንክ የግል ነው፤ ከተላከ 24 ሰዓት በኋላ ጊዜው ያበቃል። ያለው ሰው ሁሉ ቀጠሮውን ማንቀሳቀስ ወይም መሰረዝ ይችላል፤ ስለዚህ አታስተላልፉት። በየጊዜው ከ“ቀጠሮ ያስተዳድሩ” ገጽ አዲስ ሊንክ መጠየቅ ይችላሉ — ሊንኩ ብቻውን መታወቂያዎ ነው፤ የይለፍ ቃል አንጠይቅም።',
            ],
        );

        $db->execute(
            'INSERT INTO terms_sections (slug, title_en, title_am, sort_order)
             VALUES (:slug3, :title_en3, :title_am3, 3)',
            [
                ':slug3' => 'window',
                ':title_en3' => 'Changing or cancelling',
                ':title_am3' => 'ቀጠሮ መለወጥ ወይም መሰረዝ',
            ],
        );

        $sectionId3 = (int) $db->lastInsertId();

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section1_3, 1, :body_en1_3, :body_am1_3)',
            [
                ':section1_3' => $sectionId3,
                ':body_en1_3' => 'You may change or cancel your appointment yourself, without asking us, up to {{window}}. The salon\'s current rule is: “{{policy}}”',
                ':body_am1_3' => 'ቀጠሮዎን በራስዎ {{window_am}} መለወጥ ወይም መሰረዝ ይችላሉ። የሳሎኑ የአሁኑ ደንብ፦ “{{policy}}” ነው።',
            ],
        );

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section2_3, 2, :body_en2_3, :body_am2_3)',
            [
                ':section2_3' => $sectionId3,
                ':body_en2_3' => 'Use the manage link in your email. You can move the appointment as often as you need inside the window; each move releases your old time back to the salon.',
                ':body_am2_3' => 'በኢሜልዎ የሚገኘውን የማስተዳደሪያ ሊንክ ይጠቀሙ። በዚህ ጊዜ ውስጥ ቀጠሮውን በፈለጉት ያህል ማንቀሳቀስ ይችላሉ፤ በእያንዳንዱ ለውጥ የቀድሞው ሰዓትዎ ወደ ሳሎኑ ይመለሳል።',
            ],
        );

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section3_3, 3, :body_en3_3, :body_am3_3)',
            [
                ':section3_3' => $sectionId3,
                ':body_en3_3' => 'After the window closes, the appointment is the salon\'s to move. Call us and we will do what we can, but we cannot promise to fill a slot that is already too close.',
                ':body_am3_3' => 'የጊዜ ገደቡ ካለፈ በኋላ ቀጠሮውን ማንቀሳቀስ የሳሎኑ ሥራ ይሆናል። ይደውሉልን፤ የተቻለንን እናደርጋለን፤ ሆኖም በጣም የቀረበ ሰዓት መሙላት ዋስትና ልንሰጥ አንችልም።',
            ],
        );

        $db->execute(
            'INSERT INTO terms_sections (slug, title_en, title_am, sort_order)
             VALUES (:slug4, :title_en4, :title_am4, 4)',
            [
                ':slug4' => 'late',
                ':title_en4' => 'Arriving late',
                ':title_am4' => 'ዘግይተው ሲመጡ',
            ],
        );

        $sectionId4 = (int) $db->lastInsertId();

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section1_4, 1, :body_en1_4, :body_am1_4)',
            [
                ':section1_4' => $sectionId4,
                ':body_en1_4' => 'Please arrive five minutes before your appointment. If you are running late, call or message us — we will hold your time for fifteen minutes.',
                ':body_am1_4' => 'ከቀጠሮዎ አምስት ደቂቃ በፊት ይምጡ። ከመዘግየትዎ በፊት ይደውሉ ወይም ይጻፉልን — ሰዓትዎን ለአስራ አምስት ደቂቃ እንይዛለን።',
            ],
        );

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section2_4, 2, :body_en2_4, :body_am2_4)',
            [
                ':section2_4' => $sectionId4,
                ':body_en2_4' => 'After fifteen minutes we may need to shorten the service so the customers after you are not kept waiting. We will always tell you what we can still do before we begin.',
                ':body_am2_4' => 'ከአስራ አምስት ደቂቃ በኋላ ከእርስዎ በኋላ ያሉ ደንበኞች እንዳይቆዩ አገልግሎቱን ማሳጠር ሊያስፈልገን ይችላል። ከመጀመራችን በፊት ምን ማድረግ እንደምንችል ሁልጊዜ እንነግርዎታለን።',
            ],
        );

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section3_4, 3, :body_en3_4, :body_am3_4)',
            [
                ':section3_4' => $sectionId4,
                ':body_en3_4' => 'If you arrive more than thirty minutes late we may have to rebook you, because the time is no longer there to give.',
                ':body_am3_4' => 'ከሠላሳ ደቂቃ በላይ ዘግይተው ከመጡ ቀጠሮውን እንደገና ማስያዝ ሊያስፈልገን ይችላል፤ ምክንያቱም የሚሰጥ ጊዜ ከእንግዲህ አይኖርም።',
            ],
        );

        $db->execute(
            'INSERT INTO terms_sections (slug, title_en, title_am, sort_order)
             VALUES (:slug5, :title_en5, :title_am5, 5)',
            [
                ':slug5' => 'no-show',
                ':title_en5' => 'If you do not come',
                ':title_am5' => 'ካልመጡ',
            ],
        );

        $sectionId5 = (int) $db->lastInsertId();

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section1_5, 1, :body_en1_5, :body_am1_5)',
            [
                ':section1_5' => $sectionId5,
                ':body_en1_5' => 'A no-show is an appointment nobody attended and nobody cancelled. It costs the stylist the hour and the salon the customer who could have had it.',
                ':body_am1_5' => 'አለመምጣት ማለት ማንም ያልመጣበትና ያልተሰረዘ ቀጠሮ ነው። ለባለሙያው ሰዓቱን፣ ለሳሎኑም ሊያገኘው የነበረውን ደንበኛ ያሳጣል።',
            ],
        );

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section2_5, 2, :body_en2_5, :body_am2_5)',
            [
                ':section2_5' => $sectionId5,
                ':body_en2_5' => 'After two no-shows we may ask you to confirm by phone on the day before we hold another long appointment.',
                ':body_am2_5' => 'ከሁለት ጊዜ አለመምጣት በኋላ ሌላ ረጅም ቀጠሮ ከመያዛችን በፊት በቀኑ በስልክ እንዲያረጋግጡ ልንጠይቅ እንችላለን።',
            ],
        );

        $db->execute(
            'INSERT INTO terms_sections (slug, title_en, title_am, sort_order)
             VALUES (:slug6, :title_en6, :title_am6, 6)',
            [
                ':slug6' => 'salon',
                ':title_en6' => 'When the salon has to change something',
                ':title_am6' => 'ሳሎኑ ሲያስተካክል',
            ],
        );

        $sectionId6 = (int) $db->lastInsertId();

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section1_6, 1, :body_en1_6, :body_am1_6)',
            [
                ':section1_6' => $sectionId6,
                ':body_en1_6' => 'Sometimes we have to move an appointment: a stylist falls ill, water or power goes off, a closure is declared. We will contact you on the number you gave us as early as we can.',
                ':body_am1_6' => 'አንዳንድ ጊዜ ቀጠሮ ማንቀሳቀስ ይኖርብናል፦ ባለሙያ ሲታመም፣ ውሃ ወይም መብራት ሲቋረጥ፣ የመዘጋት ማስታወቂያ ሲወጣ። በተቻለ ፍጥነት በሰጡን ቁጥር እናገኝዎታለን።',
            ],
        );

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section2_6, 2, :body_en2_6, :body_am2_6)',
            [
                ':section2_6' => $sectionId6,
                ':body_en2_6' => 'You may then take a new time, or cancel without any obligation. Neither choice will be held against you.',
                ':body_am2_6' => 'ከዚያ አዲስ ሰዓት መውሰድ ወይም ያለ ምንም ግዴታ መሰረዝ ይችላሉ። ከሁለቱም ምርጫ በኋላ ምንም ቅሬታ አናስቀምጥም።',
            ],
        );

        $db->execute(
            'INSERT INTO terms_sections (slug, title_en, title_am, sort_order)
             VALUES (:slug7, :title_en7, :title_am7, 7)',
            [
                ':slug7' => 'health',
                ':title_en7' => 'Health, allergies and patch tests',
                ':title_am7' => 'ጤና፣ አለርጂ እና የቆዳ ምርመራ',
            ],
        );

        $sectionId7 = (int) $db->lastInsertId();

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section1_7, 1, :body_en1_7, :body_am1_7)',
            [
                ':section1_7' => $sectionId7,
                ':body_en1_7' => 'Please tell us before your appointment about allergies, skin conditions, pregnancy, recent surgery, medication or anything that changes how a product might affect you. It is not a formality.',
                ':body_am1_7' => 'ከቀጠሮዎ በፊት ስለ አለርጂ፣ ስለ ቆዳ ችግር፣ ስለ እርግዝና፣ ስለ ቅርብ ጊዜ ቀዶ ጥገና፣ ስለ መድኃኒት ወይም ምርቱ በእርስዎ ላይ ሊያሳድረው ስለሚችለው ተጽዕኖ ይንገሩን። ይህ ሥርዓት አይደለም።',
            ],
        );

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section2_7, 2, :body_en2_7, :body_am2_7)',
            [
                ':section2_7' => $sectionId7,
                ':body_en2_7' => 'For colour and chemical services we may ask for a patch test at least 48 hours before your first appointment with a new product. We will not proceed without one if we believe it is needed.',
                ':body_am2_7' => 'ለቀለምና ለኬሚካል አገልግሎቶች አዲስ ምርት ለመጀመሪያ ጊዜ ከሚጠቀሙበት ቀጠሮ ቢያንስ 48 ሰዓት በፊት የቆዳ ምርመራ ልንጠይቅ እንችላለን። አስፈላጊ ነው ብለን ካመንን ያለ ምርመራ አንቀጥልም።',
            ],
        );

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section3_7, 3, :body_en3_7, :body_am3_7)',
            [
                ':section3_7' => $sectionId7,
                ':body_en3_7' => 'We follow the manufacturer\'s instructions for every product. If a service cannot be done safely we will say so and suggest something else — the answer may be no.',
                ':body_am3_7' => 'ለእያንዳንዱ ምርት የአምራቹን መመሪያ እንከተላለን። አገልግሎቱ ያለ አደጋ ሊከናወን ካልቻለ እንነግርዎታለን፤ ሌላ አማራጭም እንጠቁማለን — መልሱ “አይቻልም” ሊሆን ይችላል።',
            ],
        );

        $db->execute(
            'INSERT INTO terms_sections (slug, title_en, title_am, sort_order)
             VALUES (:slug8, :title_en8, :title_am8, 8)',
            [
                ':slug8' => 'guests',
                ':title_en8' => 'Children and guests',
                ':title_am8' => 'ልጆች እና እንግዶች',
            ],
        );

        $sectionId8 = (int) $db->lastInsertId();

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section1_8, 1, :body_en1_8, :body_am1_8)',
            [
                ':section1_8' => $sectionId8,
                ':body_en1_8' => 'The salon is a calm place with hot tools, chemicals and sharp implements. Please come with whoever you need, but children must stay beside you and are your responsibility.',
                ':body_am1_8' => 'ሳሎኑ ትኩስ መሣሪያዎች፣ ኬሚካሎችና ሹል መሣሪያዎች ያሉበት ረጋ ያለ ቦታ ነው። አስፈላጊ የሆነውን ሰው አስከትለው ይምጡ፤ ልጆች ግን ከእርስዎ አጠገብ መቀመጥ አለባቸው፤ ኃላፊነቱም የእርስዎ ነው።',
            ],
        );

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section2_8, 2, :body_en2_8, :body_am2_8)',
            [
                ':section2_8' => $sectionId8,
                ':body_en2_8' => 'If you are bringing a guest to a long appointment, please tell us when you book so we can seat them properly.',
                ':body_am2_8' => 'ለረጅም ቀጠሮ እንግድ ካስከተሉ በሚያዙበት ጊዜ ይንገሩን፤ በአግባቡ ልናስቀምጣቸው እንችል ዘንድ።',
            ],
        );

        $db->execute(
            'INSERT INTO terms_sections (slug, title_en, title_am, sort_order)
             VALUES (:slug9, :title_en9, :title_am9, 9)',
            [
                ':slug9' => 'photography',
                ':title_en9' => 'Photography and social media',
                ':title_am9' => 'ፎቶግራፍ እና ማህበራዊ ሚዲያ',
            ],
        );

        $sectionId9 = (int) $db->lastInsertId();

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section1_9, 1, :body_en1_9, :body_am1_9)',
            [
                ':section1_9' => $sectionId9,
                ':body_en1_9' => 'We photograph finished work for our own portfolio and social media. We will always ask first, and we will not photograph your face or publish anything that identifies you without your clear permission.',
                ':body_am1_9' => 'የተጠናቀቀውን ሥራ ለራሳችን ፖርትፎሊዮና ለማህበራዊ ሚዲያ እናነሳለን። ሁልጊዜ አስቀድመን እንጠይቃለን፤ ግልጽ ፈቃድ ካልሰጡ ፊትዎን አናነሳም፤ ማንነትዎን የሚገልጽ ነገርም አናትም።',
            ],
        );

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section2_9, 2, :body_en2_9, :body_am2_9)',
            [
                ':section2_9' => $sectionId9,
                ':body_en2_9' => 'Saying no changes nothing about your appointment or how you are treated. If you change your mind later, tell us and we will remove the picture.',
                ':body_am2_9' => '“አይ” ማለት በቀጠሮዎ ወይም በእርስዎ ላይ በሚደረግ አያያዝ ምንም አይለውጥም። በኋላ ሐሳብዎን ከቀየሩ ይንገሩን፤ ፎቶውን እናስወግዳለን።',
            ],
        );

        $db->execute(
            'INSERT INTO terms_sections (slug, title_en, title_am, sort_order)
             VALUES (:slug10, :title_en10, :title_am10, 10)',
            [
                ':slug10' => 'privacy',
                ':title_en10' => 'Your information',
                ':title_am10' => 'የእርስዎ መረጃ',
            ],
        );

        $sectionId10 = (int) $db->lastInsertId();

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section1_10, 1, :body_en1_10, :body_am1_10)',
            [
                ':section1_10' => $sectionId10,
                ':body_en1_10' => 'We keep your name, phone number, email address (if you gave one) and the notes needed to do your service. We use them to confirm bookings, remind you, and keep a record of anything that affects your hair or skin.',
                ':body_am1_10' => 'ስምዎን፣ ስልክ ቁጥርዎን፣ ኢሜልዎን (ከሰጡ) እና አገልግሎቱን ለመስጠት የሚያስፈልጉ ማስታወሻዎችን እናስቀምጣለን። ቀጠሮ ለማረጋገጥ፣ ለማስታወሻ እና በጸጉርዎ ወይም በቆዳዎ ላይ ተጽዕኖ የሚያሳድር ነገር ለመመዝገብ እንጠቀምባቸዋለን።',
            ],
        );

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section2_10, 2, :body_en2_10, :body_am2_10)',
            [
                ':section2_10' => $sectionId10,
                ':body_en2_10' => 'We do not sell your details and we do not share them, except with the services that send our email — which only carry the message, not a marketing list.',
                ':body_am2_10' => 'መረጃዎን አንሸጥም፤ ኢሜላችንን ከሚልኩ አገልግሎቶች በስተቀር ለማንም አናካፍልም — እነሱም መልእክቱን ብቻ ይይዛሉ፤ የማስታወቂያ ዝርዝር አይደለም።',
            ],
        );

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section3_10, 3, :body_en3_10, :body_am3_10)',
            [
                ':section3_10' => $sectionId10,
                ':body_en3_10' => 'You may ask us at any time what we hold about you, and ask us to correct or delete it. Ask at the salon or use the contact details at the foot of this page.',
                ':body_am3_10' => 'ስለ እርስዎ ያለንን መረጃ በማንኛውም ጊዜ መጠየቅ፣ እንዲስተካከል ወይም እንዲሰረዝ መጠየቅ ይችላሉ። በሳሎኑ ይጠይቁ ወይም በዚህ ገጽ ግርጌ ያለውን የመገኛ መረጃ ይጠቀሙ።',
            ],
        );

        $db->execute(
            'INSERT INTO terms_sections (slug, title_en, title_am, sort_order)
             VALUES (:slug11, :title_en11, :title_am11, 11)',
            [
                ':slug11' => 'complaints',
                ':title_en11' => 'If something is not right',
                ':title_am11' => 'ችግር ካለ',
            ],
        );

        $sectionId11 = (int) $db->lastInsertId();

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section1_11, 1, :body_en1_11, :body_am1_11)',
            [
                ':section1_11' => $sectionId11,
                ':body_en1_11' => 'Tell us within seven days if you are not happy with a service. Bring your reference if you have it and show us what concerns you — we need to see the work to put it right.',
                ':body_am1_11' => 'በአገልግሎት ካልተደሰቱ በሰባት ቀናት ውስጥ ይንገሩን። የማጣቀሻ ቁጥሩ ካለዎት ይዘው ይምጡ፤ የሚያሳስብዎን ያሳዩን — ለማስተካከል ሥራውን ማየት ያስፈልገናል።',
            ],
        );

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section2_11, 2, :body_en2_11, :body_am2_11)',
            [
                ':section2_11' => $sectionId11,
                ':body_en2_11' => 'We will offer a correction, or a redo of the service concerned, where that is the fair answer. What we will not do is argue about it.',
                ':body_am2_11' => 'ማስተካከያ ወይም ሥራውን እንደገና ማድረግ ፍትሐዊው መልስ ሲሆን እናቀርባለን። የማናደርገው ነገር ስለሱ መከራከር ነው።',
            ],
        );

        $db->execute(
            'INSERT INTO terms_sections (slug, title_en, title_am, sort_order)
             VALUES (:slug12, :title_en12, :title_am12, 12)',
            [
                ':slug12' => 'law',
                ':title_en12' => 'The law that applies',
                ':title_am12' => 'የሚመለከተው ሕግ',
            ],
        );

        $sectionId12 = (int) $db->lastInsertId();

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section1_12, 1, :body_en1_12, :body_am1_12)',
            [
                ':section1_12' => $sectionId12,
                ':body_en1_12' => 'These terms are governed by the laws of the Federal Democratic Republic of Ethiopia. If a dispute cannot be settled between us, it belongs to the courts of Addis Ababa.',
                ':body_am1_12' => 'ይህ ስምምነት በኢትዮጵያ ፌዴራላዊ ዲሞክራሲያዊ ሪፐብሊክ ሕጎች ይተዳደራል። ክርክር በመካከላችን ሳይፈታ ከቀረ በአዲስ አበባ ፍርድ ቤቶች ይታያል።',
            ],
        );

        $db->execute(
            'INSERT INTO terms_clauses (section_id, sort_order, body_en, body_am)
             VALUES (:section2_12, 2, :body_en2_12, :body_am2_12)',
            [
                ':section2_12' => $sectionId12,
                ':body_en2_12' => 'If one clause here is found to be unenforceable, the rest of the terms still stand.',
                ':body_am2_12' => 'ከዚህ ስምምነት አንድ አንቀጽ የማይፈጸም ሆኖ ከተገኘ ቀሪው ስምምነት አሁንም ይሠራል።',
            ],
        );


        // The date the document last changed, shown under the text. The panel
        // keeps it current on every save.
        $db->execute(
            "INSERT INTO settings (setting_key, setting_value, updated_at)
              VALUES ('terms.updated_on', '2026-11-17', NOW())
              ON DUPLICATE KEY UPDATE setting_value = setting_value",
        );
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS terms_clauses');
        $db->execute('DROP TABLE IF EXISTS terms_sections');
        $db->execute("DELETE FROM settings WHERE setting_key = 'terms.updated_on'");
    }
};
