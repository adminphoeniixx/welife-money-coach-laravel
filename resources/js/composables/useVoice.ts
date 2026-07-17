import { ref } from 'vue';

interface SpeechCtor {
    new (): {
        lang: string;
        interimResults: boolean;
        maxAlternatives: number;
        onresult: (e: { results: Array<Array<{ transcript: string }>> }) => void;
        onend: () => void;
        onerror: () => void;
        start: () => void;
        stop: () => void;
    };
}

/**
 * Thin wrapper around the browser Web Speech API for voice input. Degrades
 * gracefully when the browser has no SpeechRecognition support.
 */
export function useVoice() {
    const w = window as unknown as { SpeechRecognition?: SpeechCtor; webkitSpeechRecognition?: SpeechCtor };
    const Ctor = w.SpeechRecognition ?? w.webkitSpeechRecognition;
    const supported = !!Ctor;
    const listening = ref(false);
    let rec: InstanceType<SpeechCtor> | null = null;

    const start = (onText: (text: string) => void) => {
        if (!Ctor) {
return;
}

        rec = new Ctor();
        rec.lang = 'en-IN';
        rec.interimResults = false;
        rec.maxAlternatives = 1;
        rec.onresult = (e) => onText(e.results[0][0].transcript);
        rec.onend = () => (listening.value = false);
        rec.onerror = () => (listening.value = false);
        listening.value = true;
        rec.start();
    };

    const stop = () => {
        rec?.stop();
        listening.value = false;
    };

    return { supported, listening, start, stop };
}

const CATEGORY_KEYWORDS: Record<string, string> = {
    fuel: 'Transport', petrol: 'Transport', diesel: 'Transport', uber: 'Transport', ola: 'Transport', cab: 'Transport',
    food: 'Food', lunch: 'Food', dinner: 'Food', breakfast: 'Food', swiggy: 'Food', zomato: 'Food', grocery: 'Food', groceries: 'Food',
    rent: 'Housing', maintenance: 'Housing',
    electricity: 'Utilities', water: 'Utilities', bill: 'Utilities', mobile: 'Utilities', internet: 'Utilities', recharge: 'Utilities',
    movie: 'Entertainment', netflix: 'Entertainment', spotify: 'Entertainment',
    shopping: 'Shopping', amazon: 'Shopping', clothes: 'Shopping',
    medicine: 'Healthcare', doctor: 'Healthcare', hospital: 'Healthcare',
    salary: 'Salary', freelance: 'Freelance',
};

const INCOME_HINTS = ['income', 'received', 'got', 'earned', 'credited', 'salary', 'refund', 'cashback'];

/**
 * Parse a spoken phrase like "spent 500 on fuel" into transaction fields.
 */
export function parseSpokenTransaction(text: string): {
    type: 'income' | 'expense';
    amount: string;
    category: string;
    description: string;
} {
    const lower = text.toLowerCase();
    const type = INCOME_HINTS.some((h) => lower.includes(h)) ? 'income' : 'expense';

    const numMatch = lower.replace(/,/g, '').match(/(\d+(?:\.\d+)?)/);
    const amount = numMatch ? numMatch[1] : '';

    let category = '';

    for (const [word, cat] of Object.entries(CATEGORY_KEYWORDS)) {
        if (lower.includes(word)) {
            category = cat;
            break;
        }
    }

    // Description = the part after "on" / "for", else the raw phrase.
    const onFor = lower.match(/(?:on|for)\s+(.+)$/);
    const description = onFor
        ? onFor[1].charAt(0).toUpperCase() + onFor[1].slice(1)
        : text.charAt(0).toUpperCase() + text.slice(1);

    return { type, amount, category, description };
}
