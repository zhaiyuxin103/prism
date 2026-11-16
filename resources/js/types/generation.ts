export type GenerationInput = {
    prompt: string;
    size: string;
    quality: string;
    reference_images: string[];
};

export type Generation = GenerationInput & {
    id: string;
    status: 'queued' | 'processing' | 'succeeded' | 'failed';
    submission_state: 'pending' | 'uncertain' | 'accepted' | 'rejected';
    estimated_cost: string;
    actual_cost: string | null;
    image_url: string | null;
    image_expired: boolean;
    error_message: string | null;
    can_resume: boolean;
    created_at: string;
};

export type GenerationPage = {
    data: Generation[];
    links: { prev: string | null; next: string | null };
    meta: { current_page: number; last_page: number; total: number };
};

export function formatCost(value: string): string {
    const [whole, fraction = ''] = value.split('.');
    return `$${whole}.${fraction.replace(/0+$/, '').padEnd(4, '0')}`;
}
