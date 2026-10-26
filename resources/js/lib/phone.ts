/**
 * A stored number (+9665XXXXXXXX) in readable groups: +966 5X XXX XXXX.
 * Other countries keep their code apart and the rest as stored.
 */
export function formatPhone(phone: string): string {
    const saudi = /^\+966(5\d)(\d{3})(\d{4})$/.exec(phone);

    if (saudi) {
        return `+966 ${saudi[1]} ${saudi[2]} ${saudi[3]}`;
    }

    return phone;
}
