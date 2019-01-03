Imports System.Data
Imports System.Data.SqlClient
Imports CrystalDecisions.CrystalReports.Engine
Imports CrystalDecisions.Shared
Public Class frmRTC

    Private Sub frmRTC_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        On Error Resume Next
        myCon.open()
        Dim crystal As New PrintRTC
        With frmBilling
            Dim objText As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(1).ReportObjects("txtbillno")
            objText.Text = .txtBillno.Text

            Dim objText1 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(1).ReportObjects("txtconsignee")
            objText1.Text = .lblAccname.Text

            Dim objText2 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(1).ReportObjects("txtaccno")
            objText2.Text = .txtAccountno.Text

            Dim objText3 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(1).ReportObjects("txtteller1")
            objText3.Text = .txtnametemp.Text

            Dim objText4 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(1).ReportObjects("txtbillno")
            objText4.Text = .txtBillno.Text

            Dim objText5 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(1).ReportObjects("txtvoyage")
            objText5.Text = .txtVoyage.Text

            Dim objText6 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(1).ReportObjects("txtstatus")
            objText6.Text = .cbType.Text

            Dim objText06 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(1).ReportObjects("txtvessel")
            objText06.Text = .txtVessel.Text

            Dim objText07 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.Section5.ReportObjects("Text7")
            objText07.Text = .txtParticulars.Text

            Dim objText7 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.Section5.ReportObjects("txttotal")
            objText7.Text = .txtTotal.Text

            Dim objText8 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.Section5.ReportObjects("txtdiscount")
            objText8.Text = .txtDiscount.Text

            Dim objText9 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.Section5.ReportObjects("txtexclusive")
            objText9.Text = .txtScipsi.Text

            Dim objText10 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.Section5.ReportObjects("txtvat")
            objText10.Text = .txtVat.Text

            Dim objText11 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.Section5.ReportObjects("txtscipsi")
            objText11.Text = .txtNet.Text

            Dim objText12 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.Section5.ReportObjects("txtppa")
            objText12.Text = .txtLessppa.Text

            Dim objText13 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.Section5.ReportObjects("txtword")
            objText13.Text = SpellNumber(.txtScipsi.Text)

            Dim objText14 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.Section5.ReportObjects("txtteller")
            objText14.Text = .txtnametemp.Text





        End With
        

        Dim ds As New DSBill

        myCmd = New SqlCommand("RTC", myCon)
        myCmd.Parameters.Add(New SqlParameter("@bill", SqlDbType.Int)).Value = frmBilling.txtBillno.Text
        myCmd.CommandType = CommandType.StoredProcedure
        myAdapter.SelectCommand = myCmd
        myAdapter.Fill(ds, "RTC")

        crystal.SetDataSource(ds)
        CrystalReportViewer1.ReportSource = crystal
        CrystalReportViewer1.Refresh()
        myCmd.Dispose()
        myCon.close()
    End Sub

End Class