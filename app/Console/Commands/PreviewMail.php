<?php

namespace App\Console\Commands;

use App\Enums\DeliveryMethod;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Mail\Commerce\OrderDeliveredMail;
use App\Mail\Commerce\OrderMail;
use App\Mail\Commerce\OrderPaidMail;
use App\Mail\Commerce\OrderRefundedMail;
use App\Mail\Commerce\OrderShippedMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Console\Command;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Dev tool: renders a transactional email to
 * storage/app/mail-previews/{type}.html (+ .txt) so the branded markup can
 * be checked without sending anything. Uses a real order (--order=uuid or
 * number) when given; otherwise an in-memory, never-saved order built from a
 * real catalog product, clearly numbered "VISTA-PREVIA". Writes nothing to
 * the database and refuses to run in production.
 *
 *   php artisan mail:preview paid
 *   php artisan mail:preview all
 *   php artisan mail:preview refunded --order=FL-000123
 */
class PreviewMail extends Command
{
    private const array TYPES = ['paid', 'refunded', 'refunded-partial', 'shipped', 'delivered', 'verify-email', 'reset-password'];

    protected $signature = 'mail:preview
        {type : paid|refunded|refunded-partial|shipped|delivered|verify-email|reset-password|all}
        {--order= : uuid or order number of a real order to render}';

    protected $description = 'Render a transactional email to storage/app/mail-previews for review (dev only)';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('mail:preview no está disponible en producción.');

            return self::FAILURE;
        }

        $type = (string) $this->argument('type');
        $types = $type === 'all' ? self::TYPES : [$type];

        if (array_diff($types, self::TYPES) !== []) {
            $this->error('Tipo desconocido. Usa: '.implode(', ', [...self::TYPES, 'all']));

            return self::FAILURE;
        }

        $order = $this->order();

        if ($order === null) {
            return self::FAILURE;
        }

        $dir = storage_path('app/mail-previews');
        File::ensureDirectoryExists($dir);

        foreach ($types as $t) {
            [$html, $text] = $this->render($t, $order);

            File::put("{$dir}/{$t}.html", $html);
            File::put("{$dir}/{$t}.txt", $text);

            $this->line("<info>✔</info> {$dir}".DIRECTORY_SEPARATOR."{$t}.html");
        }

        return self::SUCCESS;
    }

    /** @return array{0: string, 1: string} */
    private function render(string $type, Order $order): array
    {
        return match ($type) {
            'paid' => $this->renderMailable(new OrderPaidMail($order)),
            'refunded' => $this->renderMailable(new OrderRefundedMail($order, $order->total_minor, true)),
            'refunded-partial' => $this->renderMailable(new OrderRefundedMail($order, intdiv($order->total_minor, 2), false)),
            'shipped' => $this->renderMailable(new OrderShippedMail($order)),
            'delivered' => $this->renderMailable(new OrderDeliveredMail($order)),
            'verify-email' => $this->renderMessage((new VerifyEmail)->toMail($this->user())),
            'reset-password' => $this->renderMessage((new ResetPassword(Str::random(40)))->toMail($this->user())),
            default => throw new \InvalidArgumentException($type),
        };
    }

    /** @return array{0: string, 1: string} */
    private function renderMailable(OrderMail $mail): array
    {
        $content = $mail->content();

        return [
            (string) $mail->render(),
            $content->text === null ? '' : Mail::render(['text' => $content->text], $content->with),
        ];
    }

    /** @return array{0: string, 1: string} */
    private function renderMessage(MailMessage $message): array
    {
        $markdown = app(Markdown::class);

        return [
            (string) $markdown->render((string) $message->markdown, $message->data()),
            (string) $markdown->renderText((string) $message->markdown, $message->data()),
        ];
    }

    private function user(): User
    {
        $user = User::query()->first() ?? new User(['name' => 'Vista previa', 'email' => 'vista-previa@example.com']);

        return $user->exists ? $user : $user->forceFill(['id' => 0]);
    }

    private function order(): ?Order
    {
        $key = $this->option('order');

        if (filled($key)) {
            $order = Order::query()->where('uuid', $key)->orWhere('order_number', $key)->first();

            if ($order === null) {
                $this->error("No existe el pedido {$key}.");
            }

            return $order;
        }

        return $this->previewOrder();
    }

    /**
     * Never saved: a sample order around a real catalog product so the
     * thumbnail and links are real. Visibly a preview ("VISTA-PREVIA").
     */
    private function previewOrder(): Order
    {
        $product = Product::query()->with('media')->where('tracks_units', true)->first()
            ?? Product::query()->with('media')->first();

        $unit = 149900;

        $order = new Order([
            'uuid' => (string) Str::uuid(),
            'order_number' => 'VISTA-PREVIA',
            'user_id' => 0,
            'status' => OrderStatus::Confirmed,
            'payment_status' => OrderPaymentStatus::Paid,
            'fulfillment_status' => FulfillmentStatus::Unfulfilled,
            'subtotal_minor' => $unit,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => $unit,
            'currency' => 'MXN',
            'customer_snapshot' => [
                'name' => 'Vista previa',
                'email' => 'vista-previa@example.com',
                'delivery' => [
                    'method' => DeliveryMethod::Shipping->value,
                    'address' => ['line' => 'Calle y número', 'neighborhood' => 'Colonia', 'city' => 'Ciudad', 'state' => 'Estado', 'postal_code' => '00000'],
                ],
            ],
            'delivery_method' => DeliveryMethod::Shipping,
            'carrier' => 'Paquetería',
            'tracking_number' => 'GUIA-VISTA-PREVIA',
        ]);
        $order->id = 0;

        $item = new OrderItem([
            'name' => $product !== null ? $product->name : 'Finisher Legacy',
            'quantity' => 1,
            'unit_price_minor' => $unit,
            'line_total_minor' => $unit,
            'currency' => 'MXN',
        ]);
        $item->setRelation('product', $product);
        $item->setRelation('productVariant', null);

        $payment = new Payment([
            'provider' => PaymentProvider::OpenPay,
            'method' => PaymentMethod::OnlineCard,
            'status' => PaymentStatus::Paid,
            'amount_minor' => $unit,
            'currency' => 'MXN',
        ]);
        $payment->id = 0;

        $order->setRelation('items', $item->newCollection([$item]));
        $order->setRelation('payments', $payment->newCollection([$payment]));
        $order->setRelation('user', null);

        return $order;
    }
}
