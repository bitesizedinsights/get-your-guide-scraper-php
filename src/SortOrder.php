<?php

declare(strict_types=1);

namespace GYGScraper;

enum SortOrder: string
{
    case Popularity = 'popularity';
    case PriceAsc = 'price_asc';
    case PriceDesc = 'price_desc';
    case RatingDesc = 'rating_desc';
}
