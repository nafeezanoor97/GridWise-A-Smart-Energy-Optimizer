export function validateScenario(scenario) {
  const errors = [];

  // Scenario ID
  if (!scenario.scenario_id.trim()) {
    errors.push("Scenario ID is required.");
  }

  // Operator Notes
  if (
    !scenario.operator_notes ||
    scenario.operator_notes.length < 1
  ) {
    errors.push("At least one operator note is required.");
  }

  if (scenario.operator_notes.length > 3) {
    errors.push("Maximum 3 operator notes are allowed.");
  }

  scenario.operator_notes.forEach((note, index) => {
    if (!note.trim()) {
      errors.push(
        `Operator Note ${index + 1} cannot be empty.`
      );
    }
  });

  // Hours
  if (!scenario.hours || scenario.hours.length !== 24) {
    errors.push("Exactly 24 hourly records are required.");
  }

  scenario.hours.forEach((item) => {
    if (item.demand_kwh < 0) {
      errors.push(
        `Hour ${item.hour}: Demand cannot be negative.`
      );
    }

    if (item.solar_kwh < 0) {
      errors.push(
        `Hour ${item.hour}: Solar cannot be negative.`
      );
    }

    if (item.tariff_bdt_per_kwh < 0) {
      errors.push(
        `Hour ${item.hour}: Tariff cannot be negative.`
      );
    }
  });

  // Battery
  const battery = scenario.battery;

  if (battery.capacity_kwh <= 0) {
    errors.push(
      "Battery capacity must be greater than 0."
    );
  }

  if (battery.initial_energy_kwh < 0) {
    errors.push(
      "Initial battery energy cannot be negative."
    );
  }

  if (
    battery.initial_energy_kwh >
    battery.capacity_kwh
  ) {
    errors.push(
      "Initial energy cannot exceed battery capacity."
    );
  }

  if (battery.minimum_energy_kwh < 0) {
    errors.push(
      "Minimum battery energy cannot be negative."
    );
  }

  if (
    battery.minimum_energy_kwh >
    battery.capacity_kwh
  ) {
    errors.push(
      "Minimum energy cannot exceed battery capacity."
    );
  }

  if (battery.max_charge_kwh_per_hour < 0) {
    errors.push(
      "Maximum charge cannot be negative."
    );
  }

  if (battery.max_discharge_kwh_per_hour < 0) {
    errors.push(
      "Maximum discharge cannot be negative."
    );
  }

  return errors;
}