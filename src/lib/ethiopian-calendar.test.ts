import { describe, expect, it } from "vitest";
import {
  currentEthiopianYear,
  daysInEthiopianMonth,
  formatEthiopian,
  formatEthiopianClock,
  formatEthiopianClockRange,
  formatGregorian,
  isValidEthiopianDate,
  parseEthiopianIso,
  toEthiopian,
  toEthiopianClock,
  toEthiopianIso,
  toGregorian,
} from "@/lib/ethiopian-calendar";

const iso = (date: Date) =>
  `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(
    date.getDate(),
  ).padStart(2, "0")}`;

describe("toEthiopian", () => {
  it("puts Meskerem 1 on 11 September", () => {
    expect(toEthiopian("2025-09-11")).toEqual({ year: 2018, month: 1, day: 1 });
  });

  it("puts Meskerem 1 on 12 September when the next Gregorian year leaps", () => {
    // The Ethiopian millennium — 2000 EC — began on 12 September 2007.
    expect(toEthiopian("2007-09-12")).toEqual({ year: 2000, month: 1, day: 1 });
    expect(toEthiopian("2023-09-12")).toEqual({ year: 2016, month: 1, day: 1 });
  });

  it("closes the Ethiopian year on Pagume 5, or 6 in a leap year", () => {
    expect(toEthiopian("2025-09-10")).toEqual({ year: 2017, month: 13, day: 5 });
    expect(toEthiopian("2026-09-10")).toEqual({ year: 2018, month: 13, day: 5 });
    // 2015 EC is an Ethiopian leap year (2015 % 4 === 3), so Pagume has six
    // days and the sixth is the day before the next Meskerem 1 (12 Sep 2023).
    expect(toEthiopian("2023-09-06")).toEqual({ year: 2015, month: 13, day: 1 });
    expect(toEthiopian("2023-09-11")).toEqual({ year: 2015, month: 13, day: 6 });
    expect(toEthiopian("2023-09-12")).toEqual({ year: 2016, month: 1, day: 1 });
  });

  it("converts an ordinary day in Hidar", () => {
    expect(toEthiopian("2026-11-17")).toEqual({ year: 2019, month: 3, day: 8 });
    expect(toEthiopian("2026-01-01")).toEqual({ year: 2018, month: 4, day: 23 });
  });

  it("accepts a Date as well as a string", () => {
    expect(toEthiopian(new Date(2025, 8, 11))).toEqual({ year: 2018, month: 1, day: 1 });
  });

  it("refuses what it cannot read instead of guessing", () => {
    expect(toEthiopian("not a date")).toBeNull();
    expect(toEthiopian("")).toBeNull();
    expect(toEthiopian(new Date(Number.NaN))).toBeNull();
  });
});

describe("toGregorian", () => {
  it("inverts Meskerem 1", () => {
    expect(iso(toGregorian({ year: 2018, month: 1, day: 1 }))).toBe("2025-09-11");
    expect(iso(toGregorian({ year: 2000, month: 1, day: 1 }))).toBe("2007-09-12");
  });

  it("reaches the sixth day of Pagume in a leap year only", () => {
    expect(iso(toGregorian({ year: 2015, month: 13, day: 6 }))).toBe("2023-09-11");
    expect(iso(toGregorian({ year: 2018, month: 13, day: 5 }))).toBe("2026-09-10");
    // 2018 EC has five days of Pagume, so a sixth is not a date at all.
    expect(isValidEthiopianDate({ year: 2018, month: 13, day: 6 })).toBe(false);
  });

  it("ends every Ethiopian year the day before the next Meskerem 1", () => {
    for (let year = 2000; year <= 2030; year += 1) {
      const last = daysInEthiopianMonth(13, year);
      const end = toGregorian({ year, month: 13, day: last });
      const next = toGregorian({ year: year + 1, month: 1, day: 1 });
      const dayAfter = new Date(end);

      dayAfter.setDate(dayAfter.getDate() + 1);

      expect(iso(dayAfter)).toBe(iso(next));
      // A leap Ethiopian year is the one whose Pagume runs to six days.
      expect(last).toBe(year % 4 === 3 ? 6 : 5);
    }
  });
});

describe("daysInEthiopianMonth", () => {
  it("gives thirty days to the twelve months", () => {
    for (let month = 1; month <= 12; month += 1) {
      expect(daysInEthiopianMonth(month, 2018)).toBe(30);
    }
  });

  it("gives Pagume five days, or six when year % 4 === 3", () => {
    expect(daysInEthiopianMonth(13, 2015)).toBe(6);
    expect(daysInEthiopianMonth(13, 2019)).toBe(6);
    expect(daysInEthiopianMonth(13, 2016)).toBe(5);
    expect(daysInEthiopianMonth(13, 2018)).toBe(5);
  });
});

describe("round trips", () => {
  it("returns the same Gregorian day for every day over eleven years", () => {
    const cursor = new Date(2020, 0, 1);
    const end = new Date(2031, 0, 1);
    let checked = 0;

    while (cursor < end) {
      const ethiopian = toEthiopian(cursor);

      expect(ethiopian).not.toBeNull();
      expect(iso(toGregorian(ethiopian!))).toBe(iso(cursor));

      cursor.setDate(cursor.getDate() + 1);
      checked += 1;
    }

    expect(checked).toBeGreaterThan(4000);
  });

  it("returns the same Ethiopian day for every day of every year 2010–2025 EC", () => {
    for (let year = 2010; year <= 2025; year += 1) {
      const lastMonth = 13;
      for (let month = 1; month <= lastMonth; month += 1) {
        for (let day = 1; day <= daysInEthiopianMonth(month, year); day += 1) {
          const ethiopian = toEthiopian(toGregorian({ year, month, day }));

          expect(ethiopian).toEqual({ year, month, day });
        }
      }
    }
  });
});

describe("validation", () => {
  it("accepts real dates, including Pagume 6 in a leap year", () => {
    expect(isValidEthiopianDate({ year: 2018, month: 13, day: 5 })).toBe(true);
    expect(isValidEthiopianDate({ year: 2015, month: 13, day: 6 })).toBe(true);
  });

  it("rejects days past the end of the month, and a fourteenth month", () => {
    expect(isValidEthiopianDate({ year: 2018, month: 1, day: 31 })).toBe(false);
    expect(isValidEthiopianDate({ year: 2018, month: 13, day: 6 })).toBe(false);
    expect(isValidEthiopianDate({ year: 2018, month: 14, day: 1 })).toBe(false);
    expect(isValidEthiopianDate({ year: 2018, month: 0, day: 1 })).toBe(false);
    expect(isValidEthiopianDate({ year: 2018, month: 1, day: 0 })).toBe(false);
    expect(isValidEthiopianDate({ year: 2018.5, month: 1, day: 1 })).toBe(false);
  });

  it("round-trips the short ISO form", () => {
    expect(toEthiopianIso({ year: 2018, month: 3, day: 8 })).toBe("2018-03-08");
    expect(parseEthiopianIso("2018-03-08")).toEqual({ year: 2018, month: 3, day: 8 });
    expect(parseEthiopianIso("2015/13/6")).toEqual({ year: 2015, month: 13, day: 6 });
    expect(parseEthiopianIso("2016-13-06")).toBeNull();
    expect(parseEthiopianIso("nonsense")).toBeNull();
  });
});

describe("formatEthiopian", () => {
  it("prints each style", () => {
    expect(formatEthiopian("2026-11-17", "short")).toBe("08/03/2019");
    expect(formatEthiopian("2026-11-17", "medium")).toBe("Hid 8, 2019");
    expect(formatEthiopian("2026-11-17", "long")).toBe("Hidar 8, 2019 EC");
    expect(formatEthiopian("2026-11-17", "amharic")).toBe("ህዳር 8, 2019 ዓ.ም");
    expect(formatEthiopian("2026-11-17", "month-year")).toBe("Hidar 2019");
    // 17 November 2026 is a Tuesday.
    expect(formatEthiopian("2026-11-17", "full")).toBe("ማክሰኞ ህዳር 8, 2019 ዓ.ም");
  });

  it("falls back rather than printing a broken date", () => {
    expect(formatEthiopian("nope", "long")).toBe("—");
    expect(formatEthiopian("nope", "long", "")).toBe("");
  });

  it("keeps the Gregorian cross-check available", () => {
    expect(formatGregorian("2026-11-17")).toBe("17 Nov 2026");
    expect(formatGregorian("nope")).toBe("—");
  });

  it("reports the current Ethiopian year", () => {
    const now = new Date();
    const year = toEthiopian(now)?.year ?? 0;

    expect(currentEthiopianYear()).toBe(year);
  });
});

describe("the Ethiopian clock", () => {
  it("starts the Ethiopian day six hours earlier", () => {
    expect(toEthiopianClock("06:00")).toMatchObject({ hour: 12, period: "ጠዋት" });
    expect(toEthiopianClock("07:00")).toMatchObject({ hour: 1, period: "ጠዋት" });
    expect(toEthiopianClock("09:00")).toMatchObject({ hour: 3, period: "ጠዋት" });
    expect(toEthiopianClock("12:00")).toMatchObject({ hour: 6, period: "ከሰዓት" });
    expect(toEthiopianClock("18:00")).toMatchObject({ hour: 12, period: "ማታ" });
    expect(toEthiopianClock("20:00")).toMatchObject({ hour: 2, period: "ማታ" });
    expect(toEthiopianClock("00:00")).toMatchObject({ hour: 6, period: "ሌሊት" });
    expect(toEthiopianClock("23:59")).toMatchObject({ hour: 5, minute: 59, period: "ማታ" });
  });

  it("reads each period word", () => {
    expect(toEthiopianClock("05:59")?.period).toBe("ሌሊት");
    expect(toEthiopianClock("11:59")?.period).toBe("ጠዋት");
    expect(toEthiopianClock("17:59")?.period).toBe("ከሰዓት");
    expect(toEthiopianClock("18:00")?.period).toBe("ማታ");
  });

  it("labels a time the way the salon says it", () => {
    expect(toEthiopianClock("09:00")?.label).toBe("3:00 ሰዓት");
    expect(toEthiopianClock("09:00")?.full).toBe("3:00 ሰዓት ጠዋት");
    expect(formatEthiopianClock("09:00")).toBe("3:00 ሰዓት (09:00)");
    expect(formatEthiopianClock("09:00", { withPeriod: true })).toBe("3:00 ሰዓት ጠዋት (09:00)");
    expect(formatEthiopianClock("13:00")).toBe("7:00 ሰዓት (13:00)");
  });

  it("passes through what it cannot parse", () => {
    expect(formatEthiopianClock("Closed")).toBe("Closed");
    expect(toEthiopianClock("24:00")).toBeNull();
    expect(toEthiopianClock("9")).toBeNull();
    expect(toEthiopianClock("")).toBeNull();
  });

  it("prints a range such as opening hours", () => {
    expect(formatEthiopianClockRange("09:00", "20:00")).toBe("3:00 ጠዋት – 2:00 ማታ (09:00 – 20:00)");
    expect(formatEthiopianClockRange("09:00:00", "19:00:00")).toBe(
      "3:00 ጠዋት – 1:00 ማታ (09:00 – 19:00)",
    );
    // An afternoon opening cannot be mistaken for a morning one.
    expect(formatEthiopianClockRange("14:00", "22:00")).toBe("8:00 ከሰዓት – 4:00 ማታ (14:00 – 22:00)");
    expect(formatEthiopianClockRange("nope", "20:00")).toBe("nope – 20:00");
  });
});
