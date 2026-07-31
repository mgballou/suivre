import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { MealLogger, type DayMeal } from './meal-logger';

const { post, routerPost } = vi.hoisted(() => ({
    post: vi.fn(),
    routerPost: vi.fn(),
}));

// `data` is backed by real React state: the component decides whether it can
// submit from what is currently typed, so a plain object would never re-render
// and the button would stay disabled through the whole test.
vi.mock('@inertiajs/react', async () => {
    const { useState } = await import('react');

    return {
        router: { post: routerPost },
        useHttp: () => {
            const [lines, setLines] = useState<string[]>([]);

            return {
                data: { lines },
                setData: (_key: string, value: string[]) => setLines(value),
                post,
                reset: () => setLines([]),
                processing: false,
            };
        },
    };
});

const mealTypes = [
    { value: 'breakfast', label: 'Breakfast' },
    { value: 'lunch', label: 'Lunch' },
];

function type(text: string) {
    fireEvent.change(screen.getByLabelText(/one item per line/i), {
        target: { value: text },
    });
}

describe('MealLogger', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('lists a logged meal with the tags its match carried', () => {
        const meals: DayMeal[] = [
            {
                id: 1,
                type: 'Breakfast',
                time: '08:00',
                entries: [
                    { id: 1, label: 'whole milk', tags: ['Dairy'], matched: true },
                ],
            },
        ];

        render(<MealLogger date="2026-07-20" meals={meals} mealTypes={mealTypes} />);

        expect(screen.getByText('whole milk')).toBeInTheDocument();
        expect(screen.getByText('Dairy')).toBeInTheDocument();
    });

    it('says plainly when a logged line never matched the catalog', () => {
        const meals: DayMeal[] = [
            {
                id: 1,
                type: 'Snack',
                time: '16:00',
                entries: [
                    { id: 1, label: 'aunt bettys slice', tags: [], matched: false },
                ],
            },
        ];

        render(<MealLogger date="2026-07-20" meals={meals} mealTypes={mealTypes} />);

        expect(screen.getByText(/not in the catalog yet/i)).toBeInTheDocument();
    });

    it('cannot be checked until something has been typed', () => {
        render(<MealLogger date="2026-07-20" meals={[]} mealTypes={mealTypes} />);

        expect(screen.getByRole('button', { name: /check these/i })).toBeDisabled();
    });

    it('shows the suggestion for each typed line before anything is saved', async () => {
        post.mockResolvedValue({
            lines: [
                {
                    text: 'milk',
                    foodItemId: 7,
                    foodItemName: 'whole milk',
                    tags: ['Dairy'],
                    score: 0.9,
                    matched: true,
                },
            ],
        });

        render(<MealLogger date="2026-07-20" meals={[]} mealTypes={mealTypes} />);

        type('milk');
        fireEvent.click(screen.getByRole('button', { name: /check these/i }));

        expect(await screen.findByText('whole milk')).toBeInTheDocument();
        expect(routerPost).not.toHaveBeenCalled();
    });

    it('saves a confirmed line against its catalog match', async () => {
        post.mockResolvedValue({
            lines: [
                {
                    text: 'milk',
                    foodItemId: 7,
                    foodItemName: 'whole milk',
                    tags: ['Dairy'],
                    score: 0.9,
                    matched: true,
                },
            ],
        });

        render(<MealLogger date="2026-07-20" meals={[]} mealTypes={mealTypes} />);

        type('milk');
        fireEvent.click(screen.getByRole('button', { name: /check these/i }));
        await screen.findByText('whole milk');

        fireEvent.click(screen.getByLabelText('Breakfast'));
        fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        await waitFor(() => expect(routerPost).toHaveBeenCalled());

        expect(routerPost.mock.calls[0][1]).toMatchObject({
            meal_type: 'breakfast',
            entries: [{ text: 'milk', food_item_id: 7 }],
        });
    });

    it('sends a rejected suggestion as unmatched text so it reaches the queue', async () => {
        post.mockResolvedValue({
            lines: [
                {
                    text: 'milk',
                    foodItemId: 7,
                    foodItemName: 'whole milk',
                    tags: ['Dairy'],
                    score: 0.9,
                    matched: true,
                },
            ],
        });

        render(<MealLogger date="2026-07-20" meals={[]} mealTypes={mealTypes} />);

        type('milk');
        fireEvent.click(screen.getByRole('button', { name: /check these/i }));
        await screen.findByText('whole milk');

        // Unticking is the user overruling the classifier.
        fireEvent.click(screen.getByRole('checkbox'));
        fireEvent.click(screen.getByLabelText('Breakfast'));
        fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        await waitFor(() => expect(routerPost).toHaveBeenCalled());

        expect(routerPost.mock.calls[0][1]).toMatchObject({
            entries: [{ text: 'milk', food_item_id: null }],
        });
    });

    it('will not save until a meal has been picked', async () => {
        post.mockResolvedValue({
            lines: [
                {
                    text: 'toast',
                    foodItemId: null,
                    foodItemName: null,
                    tags: [],
                    score: null,
                    matched: false,
                },
            ],
        });

        render(<MealLogger date="2026-07-20" meals={[]} mealTypes={mealTypes} />);

        type('toast');
        fireEvent.click(screen.getByRole('button', { name: /check these/i }));
        await screen.findByText(/nothing in the catalog matches/i);

        expect(screen.getByRole('button', { name: /pick a meal first/i })).toBeDisabled();
    });
});
