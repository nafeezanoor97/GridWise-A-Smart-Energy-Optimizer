function BatteryForm({
  battery,
  scenario,
  setScenario,
}) {

  const updateBattery = (
    field,
    value
  ) => {

    setScenario({
      ...scenario,

      battery: {
        ...battery,
        [field]: Number(value),
      },
    });
  };

  return (
    <section className="card">

      <div className="section-title">

        <div>
          <p className="section-label">
            BATTERY
          </p>

          <h2>
            Battery Configuration
          </h2>
        </div>

      </div>


      <div className="battery-grid">

        {/* Capacity */}
        <div className="form-group">

          <label>
            Capacity (kWh)
          </label>

          <input
            type="number"
            min="0"
            value={battery.capacity_kwh}
            onChange={(e) =>
              updateBattery(
                "capacity_kwh",
                e.target.value
              )
            }
          />

        </div>


        {/* Initial Energy */}
        <div className="form-group">

          <label>
            Initial Energy (kWh)
          </label>

          <input
            type="number"
            min="0"
            value={battery.initial_energy_kwh}
            onChange={(e) =>
              updateBattery(
                "initial_energy_kwh",
                e.target.value
              )
            }
          />

        </div>


        {/* Minimum Energy */}
        <div className="form-group">

          <label>
            Minimum Energy (kWh)
          </label>

          <input
            type="number"
            min="0"
            value={battery.minimum_energy_kwh}
            onChange={(e) =>
              updateBattery(
                "minimum_energy_kwh",
                e.target.value
              )
            }
          />

        </div>


        {/* Max Charge */}
        <div className="form-group">

          <label>
            Max Charge / Hour
          </label>

          <input
            type="number"
            min="0"
            value={
              battery.max_charge_kwh_per_hour
            }
            onChange={(e) =>
              updateBattery(
                "max_charge_kwh_per_hour",
                e.target.value
              )
            }
          />

        </div>


        {/* Max Discharge */}
        <div className="form-group">

          <label>
            Max Discharge / Hour
          </label>

          <input
            type="number"
            min="0"
            value={
              battery.max_discharge_kwh_per_hour
            }
            onChange={(e) =>
              updateBattery(
                "max_discharge_kwh_per_hour",
                e.target.value
              )
            }
          />

        </div>

      </div>

    </section>
  );
}

export default BatteryForm;