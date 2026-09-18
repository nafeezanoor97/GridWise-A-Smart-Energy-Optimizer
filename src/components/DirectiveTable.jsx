function DirectiveTable({ directives }) {
  if (!directives || directives.length === 0) {
    return null;
  }

  return (
    <section className="table-section">

      <div className="section-heading">

        <p className="eyebrow">
          OPERATOR INSTRUCTIONS
        </p>

        <h2>
          Directive Interpretation
        </h2>

        <p>
          Natural-language instructions
          used during the optimization process.
        </p>

      </div>


      <div className="table-wrapper">

        <table>

          <thead>

            <tr>
              <th>
                #
              </th>

              <th>
                Operator Note
              </th>

              <th>
                Interpretation
              </th>
            </tr>

          </thead>


          <tbody>

            {directives.map(
              (directive, index) => (

                <tr key={index}>

                  <td>
                    {directive.directive_id ||
                      index + 1}
                  </td>

                  <td>
                    {directive.operator_note}
                  </td>

                  <td>
                    {directive.interpretation}
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

export default DirectiveTable;