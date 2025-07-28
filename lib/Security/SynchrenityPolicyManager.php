<?php
// Synchrenity Policy base and manager

namespace Synchrenity\Security;

class SynchrenityPolicy
{
    /**
     * Called before any ability check. Override to grant/deny all abilities for a user.
     * Return true/false to short-circuit, or null to continue.
     */
    public function before($user, $ability, ...$args)
    {
        return null;
    }

    /**
     * Called after an ability check. Override for audit/logging.
     */
    public function after($user, $ability, $result, ...$args)
    {
        // Optionally log or audit
        return $result;
    }
}

class SynchrenityPolicyManager
{
    protected $policies = [];
    protected $userResolver;
    protected $cache = [];
    protected $denyCallback;
    protected $auditCallback;

    public function __construct(callable $userResolver = null)
    {
        $this->userResolver = $userResolver;
    }

    /**
     * Register a policy for a model/class.
     * Policy class must be named {ModelName}Policy by convention.
     */
    public function register($model, $policyClass = null)
    {
        if ($policyClass === null) {
            // Guess policy class name by convention
            if (is_object($model)) {
                $model = get_class($model);
            }
            $base = is_string($model) ? (substr(strrchr($model, '\\'), 1) ?: $model) : $model;
            $policyClass = $base . 'Policy';
            if (class_exists('App\\Policies\\' . $policyClass)) {
                $policyClass = 'App\\Policies\\' . $policyClass;
            } elseif (class_exists('Synchrenity\\Policies\\' . $policyClass)) {
                $policyClass = 'Synchrenity\\Policies\\' . $policyClass;
            }
        }
        $this->policies[$model] = $policyClass;
    }

    /**
     * Register multiple policies at once.
     * Accepts [ModelClass => PolicyClass] or [ModelClass] (uses convention).
     */
    public function registerMany(array $map)
    {
        foreach ($map as $model => $policy) {
            if (is_int($model)) {
                $this->register($policy);
            } else {
                $this->register($model, $policy);
            }
        }
    }

    /**
     * Set a callback to resolve the current user.
     */
    public function setUserResolver(callable $resolver)
    {
        $this->userResolver = $resolver;
    }

    /**
     * Set a callback to run on denied authorization.
     */
    public function onDeny(callable $callback)
    {
        $this->denyCallback = $callback;
    }

    /**
     * Set a callback to run for auditing all checks.
     */
    public function onAudit(callable $callback)
    {
        $this->auditCallback = $callback;
    }

    /**
     * Get the policy instance for a model/class.
     */
    public function getPolicy($model)
    {
        if (isset($this->cache[$model])) return $this->cache[$model];
        if (!isset($this->policies[$model])) return null;
        $policy = new $this->policies[$model];
        $this->cache[$model] = $policy;
        return $policy;
    }

    /**
     * Authorize an ability for the current user and model/args.
     */
    public function authorize($ability, $model, ...$args)
    {
        $user = $this->resolveUser();
        $policy = $this->getPolicy($model);
        if (!$policy) {
            $this->audit('policy.missing', $user, $ability, $model, $args, false);
            throw new \Exception("No policy registered for model: $model");
        }
        // Before hook
        if (method_exists($policy, 'before')) {
            $result = $policy->before($user, $ability, ...$args);
            if (!is_null($result)) {
                $this->audit('policy.before', $user, $ability, $model, $args, $result);
                return $this->after($policy, $user, $ability, $result, ...$args);
            }
        }
        if (!method_exists($policy, $ability)) {
            $this->audit('policy.ability_missing', $user, $ability, $model, $args, false);
            throw new \Exception("Policy does not define ability: $ability");
        }
        $result = (bool) $policy->$ability($user, ...$args);
        $result = $this->after($policy, $user, $ability, $result, ...$args);
        $this->audit('policy.checked', $user, $ability, $model, $args, $result);
        if (!$result && $this->denyCallback) {
            call_user_func($this->denyCallback, $user, $ability, $model, $args);
        }
        return $result;
    }

    /**
     * Check if the user is authorized for the ability (alias for authorize).
     */
    public function allows($ability, $model, ...$args)
    {
        return $this->authorize($ability, $model, ...$args);
    }

    /**
     * Check if the user is NOT authorized for the ability.
     */
    public function denies($ability, $model, ...$args)
    {
        return !$this->authorize($ability, $model, ...$args);
    }

    /**
     * Run the after hook on the policy.
     */
    protected function after($policy, $user, $ability, $result, ...$args)
    {
        if (method_exists($policy, 'after')) {
            return $policy->after($user, $ability, $result, ...$args);
        }
        return $result;
    }

    /**
     * Audit all checks if an audit callback is set.
     */
    protected function audit($event, $user, $ability, $model, $args, $result)
    {
        if ($this->auditCallback) {
            call_user_func($this->auditCallback, [
                'event' => $event,
                'user' => $user,
                'ability' => $ability,
                'model' => $model,
                'args' => $args,
                'result' => $result,
                'timestamp' => time(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        }
    }

    /**
     * Resolve the current user.
     */
    protected function resolveUser()
    {
        if ($this->userResolver) {
            return call_user_func($this->userResolver);
        }
        // Default: try $_SESSION['user']
        return $_SESSION['user'] ?? null;
    }
}
