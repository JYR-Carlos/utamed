<?php

namespace Tests\Stubs;

/**
 * Stub de AsignacionPlan para testing sin BD
 * Usa nombre StudlyCase para coincidir con mappings
 */
class AsignacionPlanStub
{
    protected $planModel;
    
    public function __construct($plan = null)
    {
        $this->planModel = $plan;
    }
    
    public function plan()
    {
        return $this->planModel;
    }
    
    // Acceso como propiedad (Eloquent magic)
    public function __get($key)
    {
        if ($key === 'plan' && method_exists($this, 'plan')) {
            return $this->plan();
        }
        return null;
    }
}
