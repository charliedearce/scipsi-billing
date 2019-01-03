Imports System.Data
Imports System.Data.SqlClient
Public Class frmORCash
    Protected Overrides ReadOnly Property CreateParams() As System.Windows.Forms.CreateParams
        Get
            Const DROPSHADOW = &H20000
            Dim cParam As CreateParams = MyBase.CreateParams
            cParam.ClassStyle = cParam.ClassStyle Or DROPSHADOW
            Return cParam
        End Get
    End Property

    Private Sub txtTotalDue_TextChanged(sender As Object, e As EventArgs) Handles txtTotalDue.TextChanged
        NumberFormat(txtTotalDue)
        txtCash.Text = txtTotalDue.Text
    End Sub



    Private Sub txtChkTotalDue_TextChanged(sender As Object, e As EventArgs) Handles txtChkTotalDue.TextChanged
        NumberFormat(txtChkTotalDue)
    End Sub

    Private Sub txtCash_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtCash.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub


    Private Sub txtCash_TextChanged_1(sender As Object, e As EventArgs) Handles txtCash.TextChanged

    End Sub

    

    Private Sub frmORCash_KeyDown(sender As Object, e As KeyEventArgs) Handles Me.KeyDown
        If (e.KeyCode = Keys.Escape) Then
            Me.Dispose()
            frmor.txtNotice.Text = ""
        ElseIf (e.KeyCode = Keys.Enter) Then

            If TabControl1.SelectedIndex = 0 Then
                If txtCheckno.Text <> "" Then
                    txtCheckno.Text = ""
                End If

                If txtCash.Text = "" Then
                    frmor.txtNotice.Text = "Please enter Cash Amount."
                ElseIf Decimal.Parse(txtCash.Text) < Decimal.Parse(txtTotalDue.Text) And txtCash.Focus = True Then
                    frmor.txtNotice.Text = "Cash is not Enough for this transaction."
                ElseIf Decimal.Parse(txtCash.Text) >= Decimal.Parse(txtTotalDue.Text) And txtCash.Focus = True Then

                    frmor.txtNotice.Text = ""
                    frmPrintOR.lblclient.Text = frmor.lblAccname.Text

                    With frmor
                        frmPrintOR.lblVat.Text = .txtORVat.Text
                        frmPrintOR.lblAmt.Text = .txtORBillAmount.Text
                        frmPrintOR.lblTax.Text = .txtHTax.Text
                        frmPrintOR.lblTotal.Text = .txtNetIncome.Text
                        frmPrintOR.lblCash.Text = txtCash.Text

                        frmPrintOR.lblChange.Text = txtCash.Text - txtTotalDue.Text

                        frmPrintOR.lblclient.Text = .lblAccname.Text
                        frmPrintOR.txtword.Text = SpellNumber(.txtNetIncome.Text)
                        frmPrintOR.lblval.Text = .txtNetIncome.Text
                        .txtOrno.Enabled = False
                        .txtAccountno.Enabled = False
                        .txtRemarks.Enabled = False
                        .btnAddOr.Enabled = False
                        .btnNewOR.Enabled = True
                    End With
                    Dim result As Integer = MessageBox.Show("You want to Print this transaction?", "Caution!", MessageBoxButtons.YesNo)
                    If result = DialogResult.Yes Then
                        frmPrintOR.Show()
                    End If
                    AddOR()
                    viewORItems()
                    txtCash.Text = ""

                End If
            ElseIf TabControl1.SelectedIndex = 1 Then

                If txtCheckno.Text = "" Then
                    frmor.txtNotice.Text = "Please enter Check number."
                Else
                    frmor.txtNotice.Text = ""
                    With frmor
                        frmPrintOR.lblVat.Text = .txtORVat.Text
                        frmPrintOR.lblAmt.Text = .txtORBillAmount.Text
                        frmPrintOR.lblTax.Text = .txtHTax.Text
                        frmPrintOR.lblTotal.Text = .txtNetIncome.Text
                        frmPrintOR.lblclient.Text = .lblAccname.Text
                        frmPrintOR.txtword.Text = SpellNumber(.txtNetIncome.Text)
                        frmPrintOR.lblval.Text = .txtNetIncome.Text

                        frmPrintOR.lblCash.Text = txtChkTotalDue.Text
                        frmPrintOR.lblChange.Text = 0
                    End With
                    txtCash.Text = txtChkTotalDue.Text

                    frmPrintOR.txtCheck.Text = txtCheckno.Text
                    Dim result As Integer = MessageBox.Show("You want to Print this transaction?", "Caution!", MessageBoxButtons.YesNo)
                    If result = DialogResult.Yes Then
                        frmPrintOR.Show()
                    End If
                    AddOR()
                    viewORItems()

                    With frmor

                        .txtOrno.Enabled = False
                        .txtAccountno.Enabled = False
                        .txtRemarks.Enabled = False
                        .btnAddOr.Enabled = False
                        .btnDeposit.Enabled = False
                        .btnNewOR.Enabled = True
                    End With
                End If

            End If

        End If
    End Sub

    Private Sub frmORCash_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        Me.KeyPreview = True
        txtCash.Focus()
        txtChkTotalDue.Text = frmor.txtNetIncome.Text
        txtTotalDue.Text = frmor.txtNetIncome.Text


    End Sub

    Private Sub txtCheckno_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtCheckno.KeyPress
        'If Asc(e.KeyChar) <> 8 Then
        '    If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) Then
        '        e.Handled = True
        '    End If
        'End If
    End Sub



    Private Sub txtDiscount_GotFocus(sender As Object, e As EventArgs)

    End Sub

    Private Sub txtDiscount_KeyPress(sender As Object, e As KeyPressEventArgs)
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub txtPartialCash_KeyPress(sender As Object, e As KeyPressEventArgs)
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

End Class