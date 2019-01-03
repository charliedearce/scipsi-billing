Public Class frmBilladd
  Protected Overrides ReadOnly Property CreateParams() As System.Windows.Forms.CreateParams
        Get
            Const DROPSHADOW = &H20000
            Dim cParam As CreateParams = MyBase.CreateParams
            cParam.ClassStyle = cParam.ClassStyle Or DROPSHADOW
            Return cParam
        End Get
    End Property
    Private Sub frmBilladd_Disposed(sender As Object, e As EventArgs) Handles Me.Disposed

    End Sub





    Private Sub frmBilladd_KeyDown(sender As Object, e As KeyEventArgs) Handles Me.KeyDown
        If (e.KeyCode = Keys.Escape) Then

            Dim result As Integer = MessageBox.Show("Are you sure you want to close this Form?", "Caution!", MessageBoxButtons.YesNo)
            If result = DialogResult.Yes Then

                txtQty.Focus()
                cbServiceName.Text = ""
                txtCargo.Text = ""
                txtService.Text = ""
                txtQty.Text = ""
                txtRate.Text = 0
                txtGross.Text = 0
                cbServiceName.SelectedValue = ""
                lblCargo.Text = ""
                lblUnit.Text = ""
                frmBilling.txtNotice.Text = ""

                Me.Dispose()

            Else
                frmBilling.txtNotice.Text = ""

            End If

        ElseIf (e.KeyCode = Keys.Back) Then
            If txtQty.Text = "" Then
                txtGross.Text = 0
            End If
        ElseIf (e.KeyCode = Keys.Enter) Then
            'vService()
            'vCargo()
            If txtService.Text = "" Or txtCargo.Text = "" Or txtQty.Text = "" Or cbServiceName.Text = "" Then
                frmBilling.txtNotice.Text = "Please fill up important details in the form."
            ElseIf frmBilling.ppa.Checked = True And (txtCargo.Text = "NSCL1" Or txtCargo.Text = "NSCL2" Or txtCargo.Text = "NSCL3") Then
                MsgBox("This is a Scale transaction, PPA share is not allowed.", vbCritical, "Message")
            ElseIf frmBilling.txtNotice.Text = "Please Enter Valid Quantity and Try Again!" Then
                MsgBox("Please Enter Valid Quantity!.", MsgBoxStyle.Critical, "Validation Message!")
            Else

                txtQty.Focus()
                computation()
                'vService()
                'vCargo()
                qtyvalidate()
                addItem()
                viewItem()
                frmBilling.txtNotice.Text = ""
                cbServiceName.SelectedValue = ""

            End If


            If frmBilling.dgItems.Rows.Count > 9 Then

                frmBilling.btnAddItem.Enabled = False
                frmBilling.txtNotice.Text = "Your Bill transaction has reach to its maximum items."
            End If
        End If

    End Sub


    Private Sub frmBilladd_Load(sender As Object, e As EventArgs) Handles MyBase.Load

        Me.KeyPreview = True
    End Sub



    Private Sub txtRate_TextChanged(sender As Object, e As EventArgs)
        NumberFormat(txtRate)
    End Sub


    Private Sub txtCargo_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtCargo.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 65 Or Asc(e.KeyChar) > 90) And (Asc(e.KeyChar) < 97 Or Asc(e.KeyChar) > 122) Then
                e.Handled = True
            End If

        End If
    End Sub




    Private Sub txtCargo_LostFocus(sender As Object, e As EventArgs) Handles txtCargo.LostFocus
        vCargo()
        qtyvalidate()
    End Sub


    Private Sub txtQty_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtQty.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub



    Private Sub txtQty_TextChanged(sender As Object, e As EventArgs) Handles txtQty.TextChanged
        If txtQty.Text = "" Then
            txtGross.Text = 0
        End If
        qtyvalidate()
    End Sub



    Private Sub txtService_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtService.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) Then
                e.Handled = True
            End If

        End If
    End Sub

    Private Sub txtService_LostFocus(sender As Object, e As EventArgs) Handles txtService.LostFocus
        vService()
    End Sub


    Private Sub txtRate_TextChanged_1(sender As Object, e As EventArgs) Handles txtRate.TextChanged
        NumberFormat(txtRate)

    End Sub

    Private Sub txtGross_TextChanged(sender As Object, e As EventArgs) Handles txtGross.TextChanged
        NumberFormat(txtGross)
       
    End Sub


    Private Sub txtService_TextChanged(sender As Object, e As EventArgs) Handles txtService.TextChanged
        vServicetype()

    End Sub



    Private Sub txtCargo_TextChanged(sender As Object, e As EventArgs) Handles txtCargo.TextChanged
        qtyvalidate()
    End Sub

    Private Sub frmBilladd_LostFocus(sender As Object, e As EventArgs) Handles Me.LostFocus
        txtQty.Focus()
    End Sub

    Private Sub cbServiceName_SelectedValueChanged(sender As Object, e As EventArgs) Handles cbServiceName.SelectedValueChanged
        On Error Resume Next
        txtType.Text = cbServiceName.SelectedValue
        vCargo()
        qtyvalidate()
    End Sub


    Private Sub txtType_TextChanged(sender As Object, e As EventArgs) Handles txtType.TextChanged
        vCargo()
    End Sub


    Private Sub lblCargo_TextChanged(sender As Object, e As EventArgs) Handles lblCargo.TextChanged
        qtyvalidate()
    End Sub

    Private Sub txtDisc_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtDisc.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub



    Private Sub txtDisc_TextChanged(sender As Object, e As EventArgs) Handles txtDisc.TextChanged
        'If txtDisc.Text = "" Then
        '    txtDisc.Text = 0
        'End If
        qtyvalidate()
    End Sub

    Private Sub txtDanger_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtDanger.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub



    Private Sub txtDanger_TextChanged(sender As Object, e As EventArgs) Handles txtDanger.TextChanged
        If txtDanger.Text = "" Then
            txtDanger.Text = 0
        End If
        On Error Resume Next
        qtyvalidate()
    End Sub

    Private Sub txtRateTemp_TextChanged(sender As Object, e As EventArgs) Handles txtRateTemp.TextChanged
        NumberFormat(txtRateTemp)
    End Sub


    Private Sub lblUnit_Click(sender As Object, e As EventArgs) Handles lblUnit.Click

    End Sub
End Class


