describe("Web App Test", function () {
  it("should automate the web app", function () {

    let domain = "https://ept";

    // 1. Login
    cy.visit(domain + "/auth/login"); // replace with your login URL
    cy.get("input[name=username]").type("dm1@gmail.com"); // replace with your username field selector and username
    cy.get("input[name=password]").type("123"); // replace with your password field selector and password
    cy.get("input[name=challengeResponse]").type("zaq"); // replace with your password field selector and password
    cy.get("button[type=submit]").click(); // replace with your login button selector

    // 2. Navigate to specific page
    cy.visit(domain + "/participant/current-schemes"); // replace with your specific page URL

    // 3. Search for a specific participant in datatable
    //cy.get("input[type=search]").type("Pangani District Hospital"); // replace with your datatable search input selector and participant name
    cy.get("#currentSchemeTable .btn-success").each(($el, index, $list) => {
      // $el is a wrapped jQuery element
      cy.wrap($el).click(); // example operation
      cy.get("#isPtTestNotPerformed").select("Able to Test Panel"); // or 'Unable to Test Panel' based on your requirement

      cy.get("#receiptDate").invoke('removeAttr', 'readonly').type("12-May-2023");
      cy.get("#shipmentTestDate").invoke('removeAttr', 'readonly').type("24-May-2023");

      cy.get("#assayName").select("Xpert MTB RIF Ultra"); // or 'Xpert MTB RIF' based on your requirement

      cy.get("#assayLot").type("100065295");
      cy.get("#expiryDate").invoke('removeAttr', 'readonly').type("23-Jun-2024");
      cy.get("#geneXpertInstrument").invoke('removeAttr', 'readonly').type("23-Jun-2024"); // Date of Last GeneXpert Instrument Calibration
      cy.get("#instrumentSn").type("5");

      let mtbcArray = ["detected", "not-detected", "detected", "not-detected", "detected"];
      let rifResistanceArray = ["detected", "na", "not-detected", "na", "detected"];

      cy.get("[name='sampleId[]']").each(($el, index, $list) => {
        cy.get($el)
          .invoke('val')
          .then((val) => {
            cy.get("#sampleRow" + val + " [name='mtbcDetected[]']").select(mtbcArray[index]);
            cy.get("#sampleRow" + val + " [name='rifResistance[]']").select(rifResistanceArray[index]);
            cy.get("#sampleRow" + val + " [name='spc[]']").type(Math.random() * 39);
            cy.get("#sampleRow" + val + " [name='ISI[]']").type(Math.random() * 39);
            cy.get("#sampleRow" + val + " [name='rpoB1[]']").type(Math.random() * 39);
            cy.get("#sampleRow" + val + " [name='rpoB2[]']").type(Math.random() * 39);
            cy.get("#sampleRow" + val + " [name='rpoB3[]']").type(Math.random() * 39);
            cy.get("#sampleRow" + val + " [name='rpoB4[]']").type(Math.random() * 39);
            cy.get("#sampleRow" + val + " [name='probeD[]']").type(Math.random() * 39);
            cy.get("#sampleRow" + val + " [name='probeC[]']").type(Math.random() * 39);
            cy.get("#sampleRow" + val + " [name='probeE[]']").type(Math.random() * 39);
            cy.get("#sampleRow" + val + " [name='probeB[]']").type(Math.random() * 39);
            cy.get("#sampleRow" + val + " [name='probeA[]']").type(Math.random() * 39);
            cy.get("#sampleRow" + val + " [name='geneXpertModuleNo[]']").type((index + 1) + "123");
            cy.get("#sampleRow" + val + " [name='dateTested[]']").invoke('removeAttr', 'readonly').type("23-Jun-2024");
            cy.get("#sampleRow" + val + " [name='testerName[]']").type("Amit");
            cy.get("#sampleRow" + val + " [name='errCode[]']").type("None");
          })
      })
      cy.get("input[name=attestation][value='yes']").check();
      cy.get("#supervisorApproval").select("yes");
      cy.get("#participantSupervisor").type("John");
      cy.get("#userComments").type("Some Comment");
    });
  });

});
