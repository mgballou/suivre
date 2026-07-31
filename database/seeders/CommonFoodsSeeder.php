<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\FoodItemType;
use App\Models\Category;
use App\Models\FoodItem;
use App\Models\FoodItemAlias;
use Illuminate\Database\Seeder;

/**
 * The everyday foods a person actually types, tagged by hand.
 *
 * This is not a substitute for the Open Food Facts import (D10/D26) — it is the
 * half that import cannot do. Two reasons it has to exist:
 *
 * 1. **Research categories.** Histamine, nightshade and FODMAP have no
 *    equivalent in any product dataset, so they only ever reach a food through
 *    curation. Without them the catalog cannot support the hypotheses an
 *    inflammatory journal most wants to test.
 * 2. **Plain names.** Open Food Facts holds branded packaged goods — forty
 *    thousand siblings of "Great Value Grade A Large Eggs". Someone logging
 *    dinner types "eggs". Unless a plain row exists, that text trigram-matches
 *    whichever brand string happens to score highest, which is worse than an
 *    honest miss because it silently attaches the wrong tags.
 *
 * Idempotent on `normalized_name`, and additive on categories, so re-running is
 * safe and never strips what an operator has since curated (D26).
 */
class CommonFoodsSeeder extends Seeder
{
    public function run(): void
    {
        $categoryIds = Category::query()->pluck('id', 'slug')->all();

        foreach ($this->foods() as [$name, $slugs, $aliases]) {
            $foodItem = $this->upsertFood($name);

            $ids = array_values(array_intersect_key($categoryIds, array_flip($slugs)));

            if ($ids !== []) {
                $foodItem->categories()->syncWithoutDetaching($ids);
            }

            foreach ($aliases as $alias) {
                $this->upsertAlias($foodItem, $alias);
            }
        }
    }

    private function upsertFood(string $name): FoodItem
    {
        $existing = FoodItem::query()
            ->where('normalized_name', FoodItem::normalizeName($name))
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $foodItem = new FoodItem();

        $foodItem->fill(['name' => $name, 'type' => FoodItemType::Item]);
        $foodItem->save();

        return $foodItem;
    }

    private function upsertAlias(FoodItem $foodItem, string $alias): void
    {
        $normalized = FoodItem::normalizeName($alias);

        $taken = FoodItemAlias::query()
            ->where('food_item_id', $foodItem->id)
            ->where('normalized_alias', $normalized)
            ->exists();

        if ($normalized === '' || $normalized === $foodItem->normalized_name || $taken) {
            return;
        }

        $model = new FoodItemAlias();

        $model->fill(['food_item_id' => $foodItem->id, 'alias' => $alias]);
        $model->save();
    }

    /**
     * Name, category slugs, and synonyms worth indexing.
     *
     * Aliases lean toward the British/American split, because a catalog that
     * only knows "eggplant" is useless to someone who types "aubergine" and the
     * trigram distance between the two is far too large to bridge.
     *
     * Where a food carries a research tag the reasoning is the standard one:
     * nightshades are Solanaceae; high-FODMAP entries follow Monash's lists;
     * histamine covers aged, cured, fermented and leftover-prone foods.
     *
     * @return array<int, array{0: string, 1: array<int, string>, 2: array<int, string>}>
     */
    private function foods(): array
    {
        return [
            // Dairy
            ['Whole milk', ['dairy'], ['milk', 'full fat milk', 'full cream milk']],
            ['Skimmed milk', ['dairy'], ['skim milk', 'nonfat milk']],
            ['Butter', ['dairy'], []],
            ['Cheddar', ['dairy', 'histamine'], ['cheddar cheese']],
            ['Parmesan', ['dairy', 'histamine'], ['parmigiano']],
            ['Blue cheese', ['dairy', 'histamine'], ['stilton', 'gorgonzola', 'roquefort']],
            ['Mozzarella', ['dairy'], []],
            ['Cream cheese', ['dairy'], []],
            ['Yoghurt', ['dairy'], ['yogurt', 'greek yoghurt', 'greek yogurt']],
            ['Cream', ['dairy'], ['double cream', 'heavy cream', 'single cream']],
            ['Ice cream', ['dairy', 'added-sugar'], []],
            ['Milk chocolate', ['dairy', 'added-sugar', 'caffeine'], ['chocolate']],
            ['Dark chocolate', ['added-sugar', 'caffeine'], []],

            // Eggs
            ['Eggs', ['egg'], ['egg', 'boiled egg', 'fried egg', 'scrambled eggs', 'omelette', 'omelet']],
            ['Mayonnaise', ['egg'], ['mayo']],

            // Grains and bread
            ['Bread', ['gluten'], ['toast', 'sliced bread', 'white bread']],
            ['Wholemeal bread', ['gluten'], ['whole wheat bread', 'brown bread']],
            ['Sourdough bread', ['gluten'], ['sourdough']],
            ['Bagel', ['gluten'], []],
            ['Croissant', ['gluten', 'dairy'], []],
            ['Pasta', ['gluten', 'fodmap'], ['spaghetti', 'penne', 'macaroni', 'noodles']],
            ['Couscous', ['gluten', 'fodmap'], []],
            ['Porridge oats', ['gluten'], ['porridge', 'oatmeal', 'oats']],
            ['Breakfast cereal', ['gluten', 'added-sugar'], ['cereal']],
            ['Rice', [], ['white rice', 'basmati rice', 'jasmine rice']],
            ['Brown rice', [], []],
            ['Quinoa', [], []],
            ['Corn tortilla', [], ['tortilla']],
            ['Pizza', ['gluten', 'dairy'], []],
            ['Barley', ['gluten', 'fodmap'], []],
            ['Rye bread', ['gluten', 'fodmap'], ['rye']],

            // Meat and fish
            ['Chicken', [], ['chicken breast', 'roast chicken', 'grilled chicken']],
            ['Beef', [], ['steak', 'ground beef', 'beef mince', 'mince']],
            ['Pork', [], ['pork chop']],
            ['Lamb', [], []],
            ['Bacon', ['histamine'], []],
            ['Sausages', ['gluten', 'histamine'], ['sausage']],
            ['Ham', ['histamine'], []],
            ['Salami', ['histamine'], []],
            ['Chorizo', ['histamine', 'nightshade'], []],
            ['Salmon', [], ['smoked salmon']],
            ['Tuna', ['histamine'], ['canned tuna']],
            ['Prawns', [], ['shrimp']],
            ['Sardines', ['histamine'], []],

            // Legumes and plant protein
            ['Tofu', ['soy'], []],
            ['Tempeh', ['soy', 'histamine'], []],
            ['Soy sauce', ['soy', 'gluten', 'histamine'], ['soya sauce']],
            ['Edamame', ['soy', 'fodmap'], []],
            ['Chickpeas', ['fodmap'], ['garbanzo beans', 'hummus', 'houmous']],
            ['Lentils', ['fodmap'], []],
            ['Black beans', ['fodmap'], []],
            ['Baked beans', ['fodmap', 'added-sugar', 'nightshade'], []],
            ['Kidney beans', ['fodmap'], []],

            // Nuts and seeds
            ['Peanut butter', ['nuts'], []],
            ['Peanuts', ['nuts'], []],
            ['Almonds', ['nuts', 'fodmap'], ['almond']],
            ['Cashews', ['nuts', 'fodmap'], []],
            ['Walnuts', ['nuts'], []],
            ['Pistachios', ['nuts', 'fodmap'], []],
            ['Almond milk', ['nuts'], []],
            ['Sunflower seeds', [], []],

            // Nightshades
            ['Tomato', ['nightshade'], ['tomatoes', 'cherry tomatoes']],
            ['Tinned tomatoes', ['nightshade'], ['canned tomatoes', 'chopped tomatoes', 'passata']],
            ['Tomato sauce', ['nightshade', 'added-sugar'], ['pasta sauce', 'marinara']],
            ['Ketchup', ['nightshade', 'added-sugar'], ['tomato ketchup']],
            ['Potato', ['nightshade'], ['potatoes', 'mashed potato', 'roast potatoes']],
            ['Chips', ['nightshade'], ['french fries', 'fries']],
            ['Crisps', ['nightshade'], ['potato chips']],
            ['Aubergine', ['nightshade'], ['eggplant']],
            ['Bell pepper', ['nightshade'], ['capsicum', 'red pepper', 'green pepper', 'peppers']],
            ['Chilli', ['nightshade'], ['chili', 'chilli pepper', 'jalapeno']],
            ['Paprika', ['nightshade'], []],
            ['Cayenne pepper', ['nightshade'], ['cayenne']],

            // High-FODMAP vegetables and fruit
            ['Onion', ['fodmap'], ['onions', 'red onion', 'white onion']],
            ['Garlic', ['fodmap'], []],
            ['Leek', ['fodmap'], ['leeks']],
            ['Mushroom', ['fodmap'], ['mushrooms']],
            ['Cauliflower', ['fodmap'], []],
            ['Asparagus', ['fodmap'], []],
            ['Apple', ['fodmap'], ['apples']],
            ['Pear', ['fodmap'], ['pears']],
            ['Mango', ['fodmap'], []],
            ['Watermelon', ['fodmap'], []],
            ['Cherries', ['fodmap'], ['cherry']],
            ['Dried apricots', ['fodmap', 'added-sugar'], ['apricots']],
            ['Avocado', ['fodmap'], []],

            // Other vegetables and fruit
            ['Carrot', [], ['carrots']],
            ['Broccoli', [], []],
            ['Spinach', ['histamine'], []],
            ['Kale', [], []],
            ['Lettuce', [], ['salad', 'green salad']],
            ['Cucumber', [], []],
            ['Courgette', [], ['zucchini']],
            ['Green beans', [], []],
            ['Peas', [], []],
            ['Sweet potato', [], ['sweet potatoes', 'kumara']],
            ['Pumpkin', [], ['squash', 'butternut squash']],
            ['Banana', [], ['bananas']],
            ['Orange', [], ['oranges']],
            ['Strawberries', ['histamine'], ['strawberry']],
            ['Blueberries', [], ['blueberry']],
            ['Grapes', [], []],
            ['Pineapple', ['histamine'], []],
            ['Lemon', [], ['lemon juice']],
            ['Coriander', [], ['cilantro']],
            ['Ginger', [], []],
            ['Olives', ['histamine'], []],

            // Drinks
            ['Coffee', ['caffeine'], ['black coffee', 'espresso', 'americano', 'filter coffee']],
            ['Latte', ['caffeine', 'dairy'], ['flat white', 'cappuccino']],
            ['Instant coffee', ['caffeine'], []],
            ['Decaf coffee', [], ['decaf']],
            ['Tea', ['caffeine'], ['black tea', 'english breakfast tea', 'cup of tea']],
            ['Green tea', ['caffeine'], []],
            ['Herbal tea', [], ['peppermint tea', 'chamomile tea']],
            ['Cola', ['caffeine', 'added-sugar'], ['coke', 'pepsi']],
            ['Energy drink', ['caffeine', 'added-sugar'], []],
            ['Orange juice', ['added-sugar'], []],
            ['Beer', ['gluten', 'alcohol', 'histamine'], ['lager', 'ale', 'ipa']],
            ['Red wine', ['alcohol', 'histamine'], []],
            ['White wine', ['alcohol', 'histamine'], []],
            ['Prosecco', ['alcohol', 'histamine'], ['champagne', 'sparkling wine']],
            ['Cider', ['alcohol', 'fodmap', 'histamine'], []],
            ['Whisky', ['alcohol'], ['whiskey', 'bourbon', 'scotch']],
            ['Gin', ['alcohol'], []],
            ['Vodka', ['alcohol'], []],
            ['Water', [], ['sparkling water', 'still water']],

            // Sweets, snacks and condiments
            ['Sugar', ['added-sugar'], ['white sugar', 'caster sugar']],
            ['Honey', ['added-sugar', 'fodmap'], []],
            ['Maple syrup', ['added-sugar'], []],
            ['Jam', ['added-sugar'], ['jelly', 'strawberry jam']],
            ['Biscuits', ['gluten', 'added-sugar', 'dairy'], ['biscuit', 'cookies', 'cookie']],
            ['Cake', ['gluten', 'added-sugar', 'egg', 'dairy'], []],
            ['Doughnut', ['gluten', 'added-sugar'], ['donut']],
            ['Muffin', ['gluten', 'added-sugar', 'egg'], []],
            ['Croutons', ['gluten'], []],
            ['Granola bar', ['added-sugar', 'nuts', 'gluten'], ['cereal bar']],
            ['Soy milk', ['soy'], ['soya milk']],
            ['Oat milk', ['gluten'], []],
            ['Vinegar', ['histamine'], ['balsamic vinegar']],
            ['Mustard', [], []],
            ['Olive oil', [], []],
            ['Salt', [], ['sea salt']],
            ['Black pepper', [], []],
            ['Stock cube', ['fodmap', 'gluten'], ['bouillon', 'stock']],
            ['Sauerkraut', ['histamine', 'fodmap'], []],
            ['Kimchi', ['histamine', 'nightshade', 'fodmap'], []],
            ['Kombucha', ['histamine'], []],

            // Common composed meals people type as one thing
            ['Curry', ['nightshade', 'fodmap'], ['chicken curry', 'vegetable curry']],
            ['Stir fry', ['soy', 'fodmap'], ['stirfry']],
            ['Sandwich', ['gluten'], []],
            ['Burger', ['gluten', 'nightshade'], ['cheeseburger', 'hamburger']],
            ['Sushi', ['soy'], []],
            ['Soup', ['fodmap'], []],
            ['Salad dressing', ['histamine'], ['vinaigrette']],
            ['Roast dinner', ['nightshade'], ['sunday roast']],
            ['Lasagne', ['gluten', 'dairy', 'nightshade'], ['lasagna']],
            ['Fish and chips', ['gluten', 'nightshade'], []],
            ['Porridge with honey', ['gluten', 'added-sugar', 'fodmap'], []],
        ];
    }
}
