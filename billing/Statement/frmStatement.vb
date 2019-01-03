'Imports System.Globalization

Public Class frmStatement

    Protected Overrides ReadOnly Property CreateParams() As System.Windows.Forms.CreateParams
        Get
            Const DROPSHADOW = &H20000
            Dim cParam As CreateParams = MyBase.CreateParams
            cParam.ClassStyle = cParam.ClassStyle Or DROPSHADOW
            Return cParam
        End Get
    End Property

    Private Sub btnClose_Click(sender As Object, e As EventArgs) Handles btnClose.Click
        Me.Dispose()
    End Sub

    Private Sub btnNewBill_Click(sender As Object, e As EventArgs)

    End Sub

    Private Sub txtfcharge_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtfcharge.KeyPress
        If Asc(e.KeyChar) <> 8 And Asc(e.KeyChar) <> 46 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub txtfcharge_LostFocus(sender As Object, e As EventArgs) Handles txtfcharge.LostFocus
        Try
            NumberFormat(txtfcharge)
        Catch ex As Exception
            MsgBox("Invalid Amount!", vbExclamation, Title:="Message Alert")
            txtfcharge.Focus()
        End Try

    End Sub

    Private Sub txtfcharge_TextChanged(sender As Object, e As EventArgs) Handles txtfcharge.TextChanged
        If txtfcharge.Text = "" Then
            txtfcharge.Text = "0.00"
        End If
        If txtindi.Text = "1" Then
            overdue()
        End If
    End Sub



    Private Sub txtOverdue_TextChanged(sender As Object, e As EventArgs) Handles txtOverdue.TextChanged
        NumberFormat(txtOverdue)
        amountdue()
    End Sub

    Private Sub txtTotalamtdue_TextChanged(sender As Object, e As EventArgs) Handles txtTotalamtdue.TextChanged
        NumberFormat(txtTotalamtdue)
    End Sub

    Private Sub txtpayment_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtpayment.KeyPress
        If Asc(e.KeyChar) <> 8 And Asc(e.KeyChar) <> 46 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub txtpayment_LostFocus(sender As Object, e As EventArgs) Handles txtpayment.LostFocus
        Try
            NumberFormat(txtpayment)
        Catch ex As Exception
            MsgBox("Invalid Amount!", vbExclamation, Title:="Message Alert")
            txtpayment.Focus()
        End Try
    End Sub

    Private Sub txtpayment_TextChanged(sender As Object, e As EventArgs) Handles txtpayment.TextChanged
        If txtpayment.Text = "" Then
            txtpayment.Text = "0.00"
        End If
        If txtindi.Text = "1" Then
            overdue()
        End If
    End Sub

    Private Sub txtBal_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtBal.KeyPress
        If Asc(e.KeyChar) <> 8 And Asc(e.KeyChar) <> 46 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub txtBal_LostFocus(sender As Object, e As EventArgs) Handles txtBal.LostFocus
        Try
            NumberFormat(txtBal)
        Catch ex As Exception
            MsgBox("Invalid Amount!", vbExclamation, Title:="Message Alert")
            txtBal.Focus()
        End Try
    End Sub


    Private Sub txtBal_TextChanged(sender As Object, e As EventArgs) Handles txtBal.TextChanged
        If txtBal.Text = "" Then
            txtBal.Text = "0.00"
        End If
        If txtindi.Text = "1" Then
            overdue()
        End If
    End Sub



    Private Sub txtAccno_TextChanged(sender As Object, e As EventArgs) Handles txtAccno.TextChanged
        If txtindi.Text = "1" Then
            statementacc()
        End If
    End Sub

    Private Sub txtAccno_MaskInputRejected(sender As Object, e As MaskInputRejectedEventArgs) Handles txtAccno.MaskInputRejected

    End Sub

    Private Sub txtCharge_TextChanged(sender As Object, e As EventArgs) Handles txtCharge.TextChanged
        NumberFormat(txtCharge)
        If txtindi.Text = "1" Then
            amountdue()
        End If
    End Sub

 
    Private Sub btnNewSoa_Click(sender As Object, e As EventArgs) Handles btnNewSoa.Click
        btnDelSoa.Enabled = False
        btnNewSoa.Enabled = False
        btnEdit.Enabled = False
        txtSoano.Enabled = True
        txtBilldate.Enabled = True
        txtDuedate.Enabled = True
        txtAccno.Enabled = True
        txtBal.Enabled = True
        txtfcharge.Enabled = True
        txtpayment.Enabled = True
        txtORpaid.Enabled = True
        'txtOrpaiddate.Enabled = True
        txtAddress.Enabled = True
        txtTo.Enabled = True

        txtSoano.Text = ""
        txtBilldate.Text = ""
        txtDuedate.Text = ""
        txtAccno.Text = ""
        txtBal.Text = ""
        txtfcharge.Text = ""
        txtpayment.Text = ""
        txtORpaid.Text = ""
        'txtOrpaiddate.Text = ""
        txtAddress.Text = ""
        txtTo.Text = ""
        txtOverdue.Text = "0.00"
        txtCharge.Text = "0.00"
        txtTotalamtdue.Text = "0.00"
        txtCusname.Text = ""
        txtindi.Text = 1
        txtindi1.Text = 0
        ViewStatement()
        txtSoano.Focus()
        txtNotice.Text = ""
    End Sub

    Private Sub txtORpaid_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtORpaid.KeyPress
        If Asc(e.KeyChar) <> 8 And Asc(e.KeyChar) <> 32 And Asc(e.KeyChar) <> 35 And Asc(e.KeyChar) <> 46 And Asc(e.KeyChar) <> 47 And Asc(e.KeyChar) <> 40 And Asc(e.KeyChar) <> 41 And Asc(e.KeyChar) <> 37 And Asc(e.KeyChar) <> 38 And Asc(e.KeyChar) <> 36 And Asc(e.KeyChar) <> 60 And Asc(e.KeyChar) <> 61 And Asc(e.KeyChar) <> 62 And Asc(e.KeyChar) <> 63 And Asc(e.KeyChar) <> 45 And Asc(e.KeyChar) <> 13 And Asc(e.KeyChar) <> 44 And Asc(e.KeyChar) <> 58 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 65 Or Asc(e.KeyChar) > 90) And (Asc(e.KeyChar) < 97 Or Asc(e.KeyChar) > 122) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub txtORpaid_TextChanged(sender As Object, e As EventArgs) Handles txtORpaid.TextChanged

    End Sub

    Private Sub txtSoano_MaskInputRejected(sender As Object, e As MaskInputRejectedEventArgs) Handles txtSoano.MaskInputRejected

    End Sub

    Private Sub txtSoano_RightToLeftChanged(sender As Object, e As EventArgs) Handles txtSoano.RightToLeftChanged

    End Sub

    Private Sub txtSoano_TextChanged(sender As Object, e As EventArgs) Handles txtSoano.TextChanged
        If txtindi.Text = "1" Then
            vSoa()
        End If
    End Sub

    Private Sub btnDelBill_Click(sender As Object, e As EventArgs) Handles btnDelBill.Click
        frmStatementDellBill.ShowDialog()
    End Sub

    Private Sub btnAddBill_Click(sender As Object, e As EventArgs) Handles btnAddBill.Click
        If txtSoano.Text = "     " Or txtBilldate.Text = "  /  /" Or txtDuedate.Text = "  /  /" Or txtAccno.Text = "     -" Or txtAddress.Text = "" Or txtTo.Text = "" Then
            txtNotice.Text = "Please fill up the important fields."
        Else
            frmStatementbilllno.ShowDialog()
        End If
    End Sub

    Private Sub btnPrintSoa_Click(sender As Object, e As EventArgs) Handles btnPrintSoa.Click
        If txtSoano.Text = "     " Or txtAccno.Text = "     -" Or txtAddress.Text = "" Or txtTo.Text = "" Or txtCharge.Text = "" Then
            txtNotice.Text = "Please fill up the important fields."
        Else
            If txtindi1.Text = 0 Then
                SaveStatement()
                frmPrintStatement.ShowDialog()
                txtSoano.Enabled = False
                txtBilldate.Enabled = False
                txtDuedate.Enabled = False
                txtAccno.Enabled = False
                txtfcharge.Enabled = False
                txtpayment.Enabled = False
                txtORpaid.Enabled = False
                'txtOrpaiddate.Enabled = False
                txtBal.Enabled = False
                txtAddress.Enabled = False
                txtTo.Enabled = False

                btnAddBill.Enabled = False
                btnDelBill.Enabled = False
                btnDelSoa.Enabled = False
                btnSearchSoa.Enabled = True
                btnNewSoa.Enabled = True
                btnClose.Enabled = True

            Else
                UpdateStatement()
                frmPrintStatement.ShowDialog()
                txtSoano.Enabled = False
                txtBilldate.Enabled = False
                txtDuedate.Enabled = False
                txtAccno.Enabled = False
                txtfcharge.Enabled = False
                txtpayment.Enabled = False
                txtORpaid.Enabled = False
                'txtOrpaiddate.Enabled = False
                txtBal.Enabled = False
                txtAddress.Enabled = False
                txtTo.Enabled = False

                btnAddBill.Enabled = False
                btnDelBill.Enabled = False
                btnDelSoa.Enabled = False
                btnSearchSoa.Enabled = True
                btnNewSoa.Enabled = True
                btnClose.Enabled = True
                btnEdit.Enabled = True

            End If
        End If
    End Sub

    Private Sub btnSearchSoa_Click(sender As Object, e As EventArgs) Handles btnSearchSoa.Click
        txtindi.Text = 0
        frmSearchStatement.ShowDialog()
    End Sub

    Private Sub frmStatement_KeyDown(sender As Object, e As KeyEventArgs) Handles Me.KeyDown
        If (e.KeyCode = Keys.F1) Then
            If btnNewSoa.Enabled = True Then
                btnNewSoa_Click(sender, e)
            End If
        ElseIf (e.KeyCode = Keys.F2) Then
            If btnAddBill.Enabled = True Then
                btnAddBill_Click(sender, e)
            End If
        ElseIf (e.KeyCode = Keys.F3) Then
            If btnDelBill.Enabled = True Then
                btnDelBill_Click(sender, e)
            End If
        ElseIf (e.KeyCode = Keys.F4) Then
            If btnPrintSoa.Enabled = True Then
                btnPrintSoa_Click(sender, e)
            End If
        ElseIf (e.KeyCode = Keys.F5) Then
            If btnDelSoa.Enabled = True Then
                btnDelSoa_Click(sender, e)
            End If
        ElseIf (e.KeyCode = Keys.F6) Then
            If btnSearchSoa.Enabled = True Then
                btnSearchSoa_Click(sender, e)
            End If
        ElseIf (e.KeyCode = Keys.F7) Then
            If btnEdit.Enabled = True Then
                Button1_Click(sender, e)
            End If
        ElseIf (e.KeyCode = Keys.Escape) Then
            If btnClose.Enabled = True Then
                Me.Dispose()
            End If
        End If
    End Sub

    Private Sub frmStatement_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        Me.KeyPreview = True
    End Sub

    Private Sub btnDelSoa_Click(sender As Object, e As EventArgs) Handles btnDelSoa.Click
        Dim result As Integer = MessageBox.Show("Are you sure you want to delete this SOA #" + txtSoano.Text + " ?", "Caution!", MessageBoxButtons.YesNo)
        If result = DialogResult.Yes Then
            SoaDel()
            ViewStatement()
        End If
    End Sub

    Private Sub txtBilldate_KeyPress(sender As Object, e As KeyPressEventArgs)

    End Sub

    Private Sub txtBilldate_LostFocus(sender As Object, e As EventArgs)
        'On Error Resume Next


    End Sub

    Private Sub txtBilldate_MaskInputRejected(sender As Object, e As MaskInputRejectedEventArgs)

    End Sub

    Private Sub txtBilldate_TextChanged(sender As Object, e As EventArgs)

    End Sub

    Private Sub txtBilldate_ValueChanged(sender As Object, e As EventArgs) Handles txtBilldate.ValueChanged
        txtDuedate.Value = txtBilldate.Value.AddDays(7)
    End Sub

    Private Sub Button1_Click(sender As Object, e As EventArgs) Handles btnEdit.Click
        txtBilldate.Enabled = True
        txtDuedate.Enabled = True
        txtAccno.Enabled = True
        txtfcharge.Enabled = True
        txtpayment.Enabled = True
        txtORpaid.Enabled = True
        txtBal.Enabled = True
        txtAddress.Enabled = True
        txtTo.Enabled = True

        btnAddBill.Enabled = True
        btnDelBill.Enabled = True

        btnNewSoa.Enabled = False
        btnEdit.Enabled = False

        txtindi.Text = 1
        txtindi1.Text = 1
    End Sub

    Private Sub dgStatement_CellContentClick(sender As Object, e As DataGridViewCellEventArgs) Handles dgStatement.CellContentClick

    End Sub

    Private Sub dgStatement_CellDoubleClick(sender As Object, e As DataGridViewCellEventArgs) Handles dgStatement.CellDoubleClick
        If txtindi.Text = 0 Then
        Else
            frmVoyage.lblbillno.Text = dgStatement.CurrentRow.Cells(2).Value.ToString
            frmVoyage.txtPOnum.Text = dgStatement.CurrentRow.Cells(8).Value.ToString
            frmVoyage.txtVoyage.Text = dgStatement.CurrentRow.Cells(4).Value.ToString

            frmVoyage.ShowDialog()
        End If
    End Sub
End Class

