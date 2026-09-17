<?php

namespace App\Mqtt;

use PhpMqtt\Client\Repositories\MemoryRepository;

class SubscriptionRepository extends MemoryRepository
{
    /**
     * Only successful SUBACKs reach MemoryRepository::addSubscription in php-mqtt v2.3.2.
     *
     * @param  list<string>  $topics
     */
    public function hasAcknowledgedSubscriptions(array $topics): bool
    {
        if ($topics === []) {
            return false;
        }

        foreach ($topics as $topic) {
            $acknowledged = false;
            foreach ($this->getSubscriptionsMatchingTopic($topic) as $subscription) {
                if ($subscription->getTopicFilter() === $topic) {
                    $acknowledged = true;
                    break;
                }
            }
            if (! $acknowledged) {
                return false;
            }
        }

        return true;
    }
}
