Imports System.Data
Imports System.Data.SqlClient
Imports CrystalDecisions.CrystalReports.Engine
Imports CrystalDecisions.Shared
Public Class frmPrintStatement

    Private Sub frmPrintStatement_FormClosed(sender As Object, e As FormClosedEventArgs) Handles Me.FormClosed
        If (myCon.State = ConnectionState.Open) Then
            myCon.close()
        End If
    End Sub

    Private Sub frmPrintStatement_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        ' Try
        On Error Resume Next
        myCon.open()
        Dim crystal As New PrintStatement
        With frmStatement
            Dim billdate As Date = .txtBilldate.Text
            Dim duedate As Date = .txtDuedate.Text
            Dim objText As TextObject = crystal.ReportDefinition.Sections(1).ReportObjects("txtasof")
            objText.Text = "As of: " + Format(billdate, "MMM dd, yyyy")

            Dim objText1 As TextObject = crystal.ReportDefinition.Sections(2).ReportObjects("txtcustomer")
            objText1.Text = .txtCusname.Text

            Dim objText2 As TextObject = crystal.ReportDefinition.Sections(2).ReportObjects("txtaddress")
            objText2.Text = .txtAddress.Text

            Dim objText3 As TextObject = crystal.ReportDefinition.Sections(2).ReportObjects("txtto")
            objText3.Text = .txtTo.Text

            Dim objText4 As TextObject = crystal.ReportDefinition.Sections(2).ReportObjects("txtaccno")
            objText4.Text = .txtAccno.Text

            Dim objText5 As TextObject = crystal.ReportDefinition.Sections(2).ReportObjects("txtsoano")
            objText5.Text = .txtSoano.Text

            Dim objText6 As TextObject = crystal.ReportDefinition.Sections(2).ReportObjects("txtbilldate")
            objText6.Text = Format(billdate, "MMM dd, yyyy")

            Dim objText7 As TextObject = crystal.ReportDefinition.Sections(2).ReportObjects("txtduedate")
            objText7.Text = Format(duedate, "MMM dd, yyyy")

            Dim objText8 As TextObject = crystal.ReportDefinition.Sections(2).ReportObjects("txtbalance")
            objText8.Text = .txtBal.Text

            Dim objText9 As TextObject = crystal.ReportDefinition.Sections(2).ReportObjects("txtpayment")
            objText9.Text = .txtpayment.Text

            Dim objText10 As TextObject = crystal.ReportDefinition.Sections(2).ReportObjects("txtfcharge")
            objText10.Text = .txtfcharge.Text

            Dim objText11 As TextObject = crystal.ReportDefinition.Sections(2).ReportObjects("txtoverdue")
            objText11.Text = .txtOverdue.Text

            Dim objText12 As TextObject = crystal.ReportDefinition.Sections(2).ReportObjects("txtbilldate")
            objText12.Text = Format(billdate, "MMM dd, yyyy")

            Dim objText13 As TextObject = crystal.ReportDefinition.Sections(4).ReportObjects("txttotalamountdue")
            objText13.Text = .txtTotalamtdue.Text

            Dim objText14 As TextObject = crystal.ReportDefinition.Sections(4).ReportObjects("txtduedate1")
            objText14.Text = Format(duedate, "MMM dd, yyyy")

            Dim objText15 As TextObject = crystal.ReportDefinition.Sections(4).ReportObjects("txtcustomer1")
            objText15.Text = .txtCusname.Text

            Dim objText16 As TextObject = crystal.ReportDefinition.Sections(4).ReportObjects("txtaddress1")
            objText16.Text = .txtAddress.Text

            Dim objText17 As TextObject = crystal.ReportDefinition.Sections(4).ReportObjects("txtaccno1")
            objText17.Text = .txtAccno.Text

            Dim objText18 As TextObject = crystal.ReportDefinition.Sections(4).ReportObjects("txtsoano1")
            objText18.Text = .txtSoano.Text

            Dim objText19 As TextObject = crystal.ReportDefinition.Sections(4).ReportObjects("txtbilldate1")
            objText19.Text = Format(billdate, "MMM dd, yyyy")

            Dim objText20 As TextObject = crystal.ReportDefinition.Sections(4).ReportObjects("txtduedate2")
            objText20.Text = Format(duedate, "MMM dd, yyyy")

            Dim objText21 As TextObject = crystal.ReportDefinition.Sections(4).ReportObjects("txttotalamountdue1")
            objText21.Text = .txtTotalamtdue.Text

            Dim objText22 As TextObject = crystal.ReportDefinition.Sections(4).ReportObjects("txtcharge")
            objText22.Text = .txtCharge.Text

            Dim objText24 As TextObject = crystal.ReportDefinition.Sections(4).ReportObjects("txtemp")
            objText24.Text = frmBilling.txtnametemp.Text

            Dim objText25 As TextObject = crystal.ReportDefinition.Sections(2).ReportObjects("txtorpaid")
            objText25.Text = frmStatement.txtORpaid.Text
        End With

        Dim ds As New DSBill

        myCmd = New SqlCommand("Statement", myCon)
        myCmd.Parameters.Add(New SqlParameter("@soanum", SqlDbType.Int)).Value = frmStatement.txtSoano.Text
        myCmd.CommandType = CommandType.StoredProcedure
        myAdapter.SelectCommand = myCmd
        myAdapter.Fill(ds, "Statement")

        crystal.SetDataSource(ds)
        CrystalReportViewer1.ReportSource = crystal
        CrystalReportViewer1.Refresh()
        myCmd.Dispose()
        myCon.close()
        'Catch ex As Exception
        '    MsgBox("No Record to show...", vbInformation, "Message")
        'Finally
        '    If (myCon.State = ConnectionState.Open) Then
        '        myCon.close()
        '    End If
        'End Try

    End Sub
End Class