<?php

class Pt_Config_QuantitativeTestConfig
{
    public float $sdScalingFactor;
    public float $uncertaintyScalingFactor;
    public float $uncertaintyThreshold;

    public function __construct(float $sdScalingFactor, float $uncertaintyScalingFactor, float $uncertaintyThreshold)
    {
        $this->sdScalingFactor = $sdScalingFactor;
        $this->uncertaintyScalingFactor = $uncertaintyScalingFactor;
        $this->uncertaintyThreshold = $uncertaintyThreshold;
    }
}
