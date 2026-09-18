function ScenarioForm({ scenario, setScenario }) {

  // -----------------------------
  // Operator Note Update
  // -----------------------------
  const updateNote = (index, value) => {
    const notes = [...scenario.operator_notes];

    notes[index] = value;

    setScenario({
      ...scenario,
      operator_notes: notes,
    });
  };

  // -----------------------------
  // Add New Note
  // Maximum 3 notes
  // -----------------------------
  const addNote = () => {
    if (scenario.operator_notes.length >= 3) {
      return;
    }

    setScenario({
      ...scenario,
      operator_notes: [
        ...scenario.operator_notes,
        "",
      ],
    });
  };

  // -----------------------------
  // Remove Note
  // -----------------------------
  const removeNote = (index) => {
    const notes = scenario.operator_notes.filter(
      (_, i) => i !== index
    );

    setScenario({
      ...scenario,
      operator_notes: notes,
    });
  };

  // -----------------------------
  // Update Hour Data
  // -----------------------------
  const updateHour = (index, field, value) => {
    const hours = [...scenario.hours];

    hours[index] = {
      ...hours[index],
      [field]: Number(value),
    };

    setScenario({
      ...scenario,
      hours,
    });
  };

  return (
    <section className="card">

      {/* Section Heading */}
      <div className="section-title">
        <div>
          <p className="section-label">
            SCENARIO
          </p>

          <h2>
            Energy Scenario
          </h2>
        </div>
      </div>


      {/* Scenario ID */}
      <div className="form-group">

        <label>
          Scenario ID
        </label>

        <input
          type="text"
          value={scenario.scenario_id}
          onChange={(e) =>
            setScenario({
              ...scenario,
              scenario_id: e.target.value,
            })
          }
          placeholder="GRID-101"
        />

      </div>


      {/* Operator Notes */}
      <div className="notes-header">

        <div>
          <label>
            Operator Notes
          </label>

          <p>
            Add 1–3 natural-language instructions.
          </p>
        </div>

        <button
          type="button"
          className="small-button"
          onClick={addNote}
          disabled={
            scenario.operator_notes.length >= 3
          }
        >
          + Add Note
        </button>

      </div>


      {/* Notes */}
      <div className="notes-container">

        {scenario.operator_notes.map(
          (note, index) => (

            <div
              className="note-row"
              key={index}
            >

              <span className="note-number">
                {index + 1}
              </span>

              <input
                type="text"
                value={note}
                onChange={(e) =>
                  updateNote(
                    index,
                    e.target.value
                  )
                }
                placeholder="Example: Solar output will drop to about 20% from 1 PM to 3 PM."
              />

              {scenario.operator_notes.length > 1 && (
                <button
                  type="button"
                  className="remove-button"
                  onClick={() =>
                    removeNote(index)
                  }
                >
                  ×
                </button>
              )}

            </div>
          )
        )}

      </div>


      {/* 24 Hour Data */}
      <div className="sub-heading">
        24-Hour Energy Data
      </div>

      <p className="helper-text">
        Enter demand, available solar and grid
        tariff for each hour.
      </p>


      <div className="table-wrapper">

        <table>

          <thead>
            <tr>
              <th>Hour</th>
              <th>Demand (kWh)</th>
              <th>Solar (kWh)</th>
              <th>Tariff (BDT/kWh)</th>
            </tr>
          </thead>

          <tbody>

            {scenario.hours.map(
              (item, index) => (

                <tr key={item.hour}>

                  <td className="hour-cell">
                    {String(item.hour).padStart(
                      2,
                      "0"
                    )}
                  </td>

                  <td>
                    <input
                      type="number"
                      min="0"
                      value={item.demand_kwh}
                      onChange={(e) =>
                        updateHour(
                          index,
                          "demand_kwh",
                          e.target.value
                        )
                      }
                    />
                  </td>

                  <td>
                    <input
                      type="number"
                      min="0"
                      value={item.solar_kwh}
                      onChange={(e) =>
                        updateHour(
                          index,
                          "solar_kwh",
                          e.target.value
                        )
                      }
                    />
                  </td>

                  <td>
                    <input
                      type="number"
                      min="0"
                      value={item.tariff_bdt_per_kwh}
                      onChange={(e) =>
                        updateHour(
                          index,
                          "tariff_bdt_per_kwh",
                          e.target.value
                        )
                      }
                    />
                  </td>

                </tr>

              )
            )}

          </tbody>

        </table>

      </div>

    </section>
  );
}

export default ScenarioForm;