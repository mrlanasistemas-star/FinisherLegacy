<?php

namespace App\Enums;

/**
 * Machine-readable codes for the general `/api/v1/*` surface's
 * `{"error": {"code", ...}}` contract (docs/api/v1.md §Errores) — mirrors
 * App\Enums\DeviceErrorCode's shape for the Device API, kept as a
 * separate enum because the Device API's contract is already documented/
 * shipped and must not shift. A client branches on `code`, never on the
 * human `message` text, which is allowed to change.
 */
enum ApiErrorCode: string
{
    case ValidationFailed = 'VALIDATION_FAILED';
    case Unauthenticated = 'UNAUTHENTICATED';
    case Forbidden = 'FORBIDDEN';
    case NotFound = 'NOT_FOUND';
    case Conflict = 'CONFLICT';
    case TooManyRequests = 'TOO_MANY_REQUESTS';
    case HttpError = 'HTTP_ERROR';
    case InternalError = 'INTERNAL_ERROR';

    case PlateAlreadyExists = 'PLATE_ALREADY_EXISTS';
    case PlateTemplateMissing = 'PLATE_TEMPLATE_MISSING';
    case ParticipantNotEligible = 'PARTICIPANT_NOT_ELIGIBLE';

    // Commerce ecosystem (brief §145).
    case ProductOutOfStock = 'PRODUCT_OUT_OF_STOCK';
    case ProductUnavailable = 'PRODUCT_UNAVAILABLE';
    case PriceNotAvailable = 'PRICE_NOT_AVAILABLE';
    case OrderNotPayable = 'ORDER_NOT_PAYABLE';
    case PaymentAmountMismatch = 'PAYMENT_AMOUNT_MISMATCH';
    case PaymentAlreadyRecorded = 'PAYMENT_ALREADY_RECORDED';
    case PaymentDeclined = 'PAYMENT_DECLINED';
    case LegacyPlateNotPaid = 'LEGACY_PLATE_NOT_PAID';
    case LegacyPlateAlreadyExists = 'LEGACY_PLATE_ALREADY_EXISTS';
    case LegacyPlatePresaleDuplicate = 'LEGACY_PLATE_PRESALE_DUPLICATE';
    case MediaLimitReached = 'MEDIA_LIMIT_REACHED';
    case MediaTooLarge = 'MEDIA_TOO_LARGE';
    case AssetAlreadyClaimed = 'ASSET_ALREADY_CLAIMED';
    case EventDataSourceNotConfigured = 'EVENT_DATA_SOURCE_NOT_CONFIGURED';
    case ProviderConnectionFailed = 'PROVIDER_CONNECTION_FAILED';
    case InvalidWebhookSignature = 'INVALID_WEBHOOK_SIGNATURE';
    case EventGearAlreadyAssigned = 'EVENT_GEAR_ALREADY_ASSIGNED';
    case CouponNotApplicable = 'COUPON_NOT_APPLICABLE';

    // Final hardening round (consolidation brief §2-§13).
    case LegacyPlateEventRequired = 'LEGACY_PLATE_EVENT_REQUIRED';
    case LegacyPlateModelRequired = 'LEGACY_PLATE_MODEL_REQUIRED';
    case LegacyPlateModelUnavailable = 'LEGACY_PLATE_MODEL_UNAVAILABLE';
    case LegacyPlateQuantityInvalid = 'LEGACY_PLATE_QUANTITY_INVALID';
    case CartEventMismatch = 'CART_EVENT_MISMATCH';
    case PriceCurrencyMismatch = 'PRICE_CURRENCY_MISMATCH';
    case OrderExpired = 'ORDER_EXPIRED';
}
