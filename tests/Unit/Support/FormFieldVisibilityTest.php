<?php

namespace Tests\Unit\Support;

use App\Support\FormFieldVisibility;
use PHPUnit\Framework\TestCase;

class FormFieldVisibilityTest extends TestCase
{
    public function test_scalar_visibility_operators_match_form_runtime(): void
    {
        $values = ['status' => 'Approved'];

        $this->assertTrue(FormFieldVisibility::isVisible([
            'field' => 'status', 'operator' => 'equals', 'values' => ['Approved'],
        ], $values));
        $this->assertFalse(FormFieldVisibility::isVisible([
            'field' => 'status', 'operator' => 'not_equals', 'values' => ['Approved'],
        ], $values));
        $this->assertTrue(FormFieldVisibility::isVisible([
            'field' => 'status', 'operator' => 'in', 'values' => "Pending,\nApproved",
        ], $values));
        $this->assertFalse(FormFieldVisibility::isVisible([
            'field' => 'status', 'operator' => 'not_in', 'values' => ['Approved', 'Rejected'],
        ], $values));
    }

    public function test_array_visibility_operators_match_form_runtime(): void
    {
        $values = ['products' => ['Card', 'Loan']];

        $this->assertTrue(FormFieldVisibility::isVisible([
            'field' => 'products', 'operator' => 'equals', 'values' => ['Card'],
        ], $values));
        $this->assertTrue(FormFieldVisibility::isVisible([
            'field' => 'products', 'operator' => 'in', 'values' => ['Savings', 'Loan'],
        ], $values));
        $this->assertFalse(FormFieldVisibility::isVisible([
            'field' => 'products', 'operator' => 'not_in', 'values' => ['Loan'],
        ], $values));
    }

    public function test_incomplete_and_unknown_rules_remain_visible(): void
    {
        $this->assertTrue(FormFieldVisibility::isVisible(null, []));
        $this->assertTrue(FormFieldVisibility::isVisible([
            'field' => 'status', 'operator' => 'unknown', 'values' => ['Approved'],
        ], ['status' => 'Approved']));
    }
}
