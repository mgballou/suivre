import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { LoggedTags } from './logged-tags';

describe('LoggedTags', () => {
    it('names each tag with the days it appeared on', () => {
        render(
            <LoggedTags
                tags={[
                    { name: 'Dairy', slug: 'dairy', days: 18 },
                    { name: 'Gluten', slug: 'gluten', days: 9 },
                ]}
                windowDays={30}
            />,
        );

        expect(screen.getByText('Dairy')).toBeInTheDocument();
        expect(screen.getByText('18 of 30 days')).toBeInTheDocument();
        expect(screen.getByText('9 of 30 days')).toBeInTheDocument();
    });

    it('explains an empty list rather than showing a blank panel', () => {
        render(<LoggedTags tags={[]} windowDays={30} />);

        expect(
            screen.getByText(/once meals are logged and matched/i),
        ).toBeInTheDocument();
    });
});
