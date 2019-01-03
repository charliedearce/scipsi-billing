<Global.Microsoft.VisualBasic.CompilerServices.DesignerGenerated()> _
Partial Class frmBilladd
    Inherits System.Windows.Forms.Form

    'Form overrides dispose to clean up the component list.
    <System.Diagnostics.DebuggerNonUserCode()> _
    Protected Overrides Sub Dispose(ByVal disposing As Boolean)
        Try
            If disposing AndAlso components IsNot Nothing Then
                components.Dispose()
            End If
        Finally
            MyBase.Dispose(disposing)
        End Try
    End Sub

    'Required by the Windows Form Designer
    Private components As System.ComponentModel.IContainer

    'NOTE: The following procedure is required by the Windows Form Designer
    'It can be modified using the Windows Form Designer.  
    'Do not modify it using the code editor.
    <System.Diagnostics.DebuggerStepThrough()> _
    Private Sub InitializeComponent()
        Dim resources As System.ComponentModel.ComponentResourceManager = New System.ComponentModel.ComponentResourceManager(GetType(frmBilladd))
        Me.Label12 = New System.Windows.Forms.Label()
        Me.GroupBox1 = New System.Windows.Forms.GroupBox()
        Me.txtRateTemp = New System.Windows.Forms.TextBox()
        Me.txtGrosstemp = New System.Windows.Forms.TextBox()
        Me.Label4 = New System.Windows.Forms.Label()
        Me.txtDanger = New System.Windows.Forms.TextBox()
        Me.txtDisc = New System.Windows.Forms.TextBox()
        Me.charge = New System.Windows.Forms.TextBox()
        Me.scipsi = New System.Windows.Forms.TextBox()
        Me.tax = New System.Windows.Forms.TextBox()
        Me.net = New System.Windows.Forms.TextBox()
        Me.ppa = New System.Windows.Forms.TextBox()
        Me.cbServiceName = New System.Windows.Forms.ComboBox()
        Me.Label2 = New System.Windows.Forms.Label()
        Me.txtType = New System.Windows.Forms.TextBox()
        Me.txtCargo = New System.Windows.Forms.TextBox()
        Me.txtService = New System.Windows.Forms.TextBox()
        Me.lblUnit = New System.Windows.Forms.Label()
        Me.txtGross = New System.Windows.Forms.TextBox()
        Me.txtRate = New System.Windows.Forms.TextBox()
        Me.Label1 = New System.Windows.Forms.Label()
        Me.lblCargo = New System.Windows.Forms.Label()
        Me.txtQty = New System.Windows.Forms.TextBox()
        Me.Label17 = New System.Windows.Forms.Label()
        Me.Label16 = New System.Windows.Forms.Label()
        Me.Label15 = New System.Windows.Forms.Label()
        Me.Label14 = New System.Windows.Forms.Label()
        Me.Label13 = New System.Windows.Forms.Label()
        Me.Label3 = New System.Windows.Forms.Label()
        Me.GroupBox1.SuspendLayout()
        Me.SuspendLayout()
        '
        'Label12
        '
        Me.Label12.AutoSize = True
        Me.Label12.Font = New System.Drawing.Font("Arial Narrow", 20.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label12.Location = New System.Drawing.Point(-197, 132)
        Me.Label12.Name = "Label12"
        Me.Label12.Size = New System.Drawing.Size(133, 31)
        Me.Label12.TabIndex = 53
        Me.Label12.Text = "QUANTITY:"
        '
        'GroupBox1
        '
        Me.GroupBox1.Controls.Add(Me.txtRateTemp)
        Me.GroupBox1.Controls.Add(Me.txtGrosstemp)
        Me.GroupBox1.Controls.Add(Me.Label4)
        Me.GroupBox1.Controls.Add(Me.txtDanger)
        Me.GroupBox1.Controls.Add(Me.txtDisc)
        Me.GroupBox1.Controls.Add(Me.charge)
        Me.GroupBox1.Controls.Add(Me.scipsi)
        Me.GroupBox1.Controls.Add(Me.tax)
        Me.GroupBox1.Controls.Add(Me.net)
        Me.GroupBox1.Controls.Add(Me.ppa)
        Me.GroupBox1.Controls.Add(Me.cbServiceName)
        Me.GroupBox1.Controls.Add(Me.Label2)
        Me.GroupBox1.Controls.Add(Me.txtType)
        Me.GroupBox1.Controls.Add(Me.txtCargo)
        Me.GroupBox1.Controls.Add(Me.txtService)
        Me.GroupBox1.Controls.Add(Me.lblUnit)
        Me.GroupBox1.Controls.Add(Me.txtGross)
        Me.GroupBox1.Controls.Add(Me.txtRate)
        Me.GroupBox1.Controls.Add(Me.Label1)
        Me.GroupBox1.Controls.Add(Me.lblCargo)
        Me.GroupBox1.Controls.Add(Me.txtQty)
        Me.GroupBox1.Controls.Add(Me.Label17)
        Me.GroupBox1.Controls.Add(Me.Label16)
        Me.GroupBox1.Controls.Add(Me.Label15)
        Me.GroupBox1.Controls.Add(Me.Label14)
        Me.GroupBox1.Controls.Add(Me.Label13)
        Me.GroupBox1.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox1.Location = New System.Drawing.Point(11, 33)
        Me.GroupBox1.Name = "GroupBox1"
        Me.GroupBox1.Size = New System.Drawing.Size(619, 336)
        Me.GroupBox1.TabIndex = 68
        Me.GroupBox1.TabStop = False
        Me.GroupBox1.Text = "ITEM INFO"
        '
        'txtRateTemp
        '
        Me.txtRateTemp.Location = New System.Drawing.Point(524, 0)
        Me.txtRateTemp.Name = "txtRateTemp"
        Me.txtRateTemp.Size = New System.Drawing.Size(56, 22)
        Me.txtRateTemp.TabIndex = 92
        Me.txtRateTemp.Text = "0"
        Me.txtRateTemp.Visible = False
        '
        'txtGrosstemp
        '
        Me.txtGrosstemp.Location = New System.Drawing.Point(489, 0)
        Me.txtGrosstemp.Name = "txtGrosstemp"
        Me.txtGrosstemp.Size = New System.Drawing.Size(29, 22)
        Me.txtGrosstemp.TabIndex = 83
        Me.txtGrosstemp.Text = "0"
        Me.txtGrosstemp.Visible = False
        '
        'Label4
        '
        Me.Label4.AutoSize = True
        Me.Label4.Font = New System.Drawing.Font("Segoe UI", 20.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label4.Location = New System.Drawing.Point(6, 170)
        Me.Label4.Name = "Label4"
        Me.Label4.Size = New System.Drawing.Size(157, 37)
        Me.Label4.TabIndex = 91
        Me.Label4.Text = "DANGER %:"
        '
        'txtDanger
        '
        Me.txtDanger.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtDanger.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtDanger.Font = New System.Drawing.Font("Segoe UI", 20.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtDanger.ForeColor = System.Drawing.Color.Red
        Me.txtDanger.Location = New System.Drawing.Point(208, 170)
        Me.txtDanger.MaxLength = 4
        Me.txtDanger.Name = "txtDanger"
        Me.txtDanger.Size = New System.Drawing.Size(50, 36)
        Me.txtDanger.TabIndex = 6
        Me.txtDanger.Text = "0"
        '
        'txtDisc
        '
        Me.txtDisc.AutoCompleteMode = System.Windows.Forms.AutoCompleteMode.SuggestAppend
        Me.txtDisc.AutoCompleteSource = System.Windows.Forms.AutoCompleteSource.CustomSource
        Me.txtDisc.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtDisc.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtDisc.Font = New System.Drawing.Font("Arial", 20.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtDisc.ForeColor = System.Drawing.Color.Red
        Me.txtDisc.Location = New System.Drawing.Point(590, 0)
        Me.txtDisc.MaxLength = 8
        Me.txtDisc.Name = "txtDisc"
        Me.txtDisc.Size = New System.Drawing.Size(29, 32)
        Me.txtDisc.TabIndex = 5
        Me.txtDisc.Visible = False
        '
        'charge
        '
        Me.charge.Location = New System.Drawing.Point(348, 0)
        Me.charge.Name = "charge"
        Me.charge.Size = New System.Drawing.Size(29, 22)
        Me.charge.TabIndex = 87
        Me.charge.Text = "0"
        Me.charge.Visible = False
        '
        'scipsi
        '
        Me.scipsi.Location = New System.Drawing.Point(313, 0)
        Me.scipsi.Name = "scipsi"
        Me.scipsi.Size = New System.Drawing.Size(29, 22)
        Me.scipsi.TabIndex = 86
        Me.scipsi.Text = "0"
        Me.scipsi.Visible = False
        '
        'tax
        '
        Me.tax.Location = New System.Drawing.Point(278, 0)
        Me.tax.Name = "tax"
        Me.tax.Size = New System.Drawing.Size(29, 22)
        Me.tax.TabIndex = 85
        Me.tax.Text = "0"
        Me.tax.Visible = False
        '
        'net
        '
        Me.net.Location = New System.Drawing.Point(243, 0)
        Me.net.Name = "net"
        Me.net.Size = New System.Drawing.Size(29, 22)
        Me.net.TabIndex = 84
        Me.net.Text = "0"
        Me.net.Visible = False
        '
        'ppa
        '
        Me.ppa.Location = New System.Drawing.Point(208, 0)
        Me.ppa.Name = "ppa"
        Me.ppa.Size = New System.Drawing.Size(29, 22)
        Me.ppa.TabIndex = 83
        Me.ppa.Text = "0"
        Me.ppa.Visible = False
        '
        'cbServiceName
        '
        Me.cbServiceName.BackColor = System.Drawing.Color.White
        Me.cbServiceName.DropDownStyle = System.Windows.Forms.ComboBoxStyle.DropDownList
        Me.cbServiceName.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.cbServiceName.Font = New System.Drawing.Font("Segoe UI", 14.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.cbServiceName.ForeColor = System.Drawing.Color.Purple
        Me.cbServiceName.Location = New System.Drawing.Point(203, 115)
        Me.cbServiceName.Name = "cbServiceName"
        Me.cbServiceName.Size = New System.Drawing.Size(410, 33)
        Me.cbServiceName.TabIndex = 4
        '
        'Label2
        '
        Me.Label2.AutoSize = True
        Me.Label2.Font = New System.Drawing.Font("Segoe UI", 20.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label2.Location = New System.Drawing.Point(6, 108)
        Me.Label2.Name = "Label2"
        Me.Label2.Size = New System.Drawing.Size(187, 37)
        Me.Label2.TabIndex = 83
        Me.Label2.Text = "SERVICE TYPE:"
        '
        'txtType
        '
        Me.txtType.Location = New System.Drawing.Point(383, 0)
        Me.txtType.Name = "txtType"
        Me.txtType.Size = New System.Drawing.Size(100, 22)
        Me.txtType.TabIndex = 82
        Me.txtType.Visible = False
        '
        'txtCargo
        '
        Me.txtCargo.AutoCompleteMode = System.Windows.Forms.AutoCompleteMode.SuggestAppend
        Me.txtCargo.AutoCompleteSource = System.Windows.Forms.AutoCompleteSource.CustomSource
        Me.txtCargo.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtCargo.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtCargo.Font = New System.Drawing.Font("Segoe UI", 20.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtCargo.ForeColor = System.Drawing.Color.Purple
        Me.txtCargo.Location = New System.Drawing.Point(201, 46)
        Me.txtCargo.MaxLength = 6
        Me.txtCargo.Name = "txtCargo"
        Me.txtCargo.Size = New System.Drawing.Size(113, 36)
        Me.txtCargo.TabIndex = 2
        '
        'txtService
        '
        Me.txtService.AutoCompleteMode = System.Windows.Forms.AutoCompleteMode.SuggestAppend
        Me.txtService.AutoCompleteSource = System.Windows.Forms.AutoCompleteSource.CustomSource
        Me.txtService.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtService.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtService.Font = New System.Drawing.Font("Segoe UI", 20.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtService.ForeColor = System.Drawing.Color.Purple
        Me.txtService.Location = New System.Drawing.Point(201, 77)
        Me.txtService.MaxLength = 6
        Me.txtService.Name = "txtService"
        Me.txtService.Size = New System.Drawing.Size(113, 36)
        Me.txtService.TabIndex = 3
        '
        'lblUnit
        '
        Me.lblUnit.AutoSize = True
        Me.lblUnit.Font = New System.Drawing.Font("Segoe UI", 20.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblUnit.ForeColor = System.Drawing.Color.Red
        Me.lblUnit.Location = New System.Drawing.Point(201, 139)
        Me.lblUnit.Name = "lblUnit"
        Me.lblUnit.Size = New System.Drawing.Size(0, 37)
        Me.lblUnit.TabIndex = 81
        '
        'txtGross
        '
        Me.txtGross.BackColor = System.Drawing.Color.White
        Me.txtGross.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtGross.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtGross.Font = New System.Drawing.Font("Segoe UI", 36.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtGross.ForeColor = System.Drawing.Color.Navy
        Me.txtGross.Location = New System.Drawing.Point(232, 263)
        Me.txtGross.MaxLength = 30
        Me.txtGross.Name = "txtGross"
        Me.txtGross.ReadOnly = True
        Me.txtGross.Size = New System.Drawing.Size(379, 64)
        Me.txtGross.TabIndex = 80
        Me.txtGross.TabStop = False
        Me.txtGross.Text = "0000000000000000000"
        Me.txtGross.TextAlign = System.Windows.Forms.HorizontalAlignment.Center
        '
        'txtRate
        '
        Me.txtRate.BackColor = System.Drawing.Color.White
        Me.txtRate.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtRate.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtRate.Font = New System.Drawing.Font("Segoe UI", 36.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtRate.ForeColor = System.Drawing.Color.Navy
        Me.txtRate.Location = New System.Drawing.Point(232, 208)
        Me.txtRate.MaxLength = 30
        Me.txtRate.Name = "txtRate"
        Me.txtRate.ReadOnly = True
        Me.txtRate.Size = New System.Drawing.Size(379, 64)
        Me.txtRate.TabIndex = 79
        Me.txtRate.TabStop = False
        Me.txtRate.Text = "0000000000000000000"
        Me.txtRate.TextAlign = System.Windows.Forms.HorizontalAlignment.Center
        '
        'Label1
        '
        Me.Label1.AutoSize = True
        Me.Label1.Font = New System.Drawing.Font("Segoe UI", 20.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label1.Location = New System.Drawing.Point(6, 16)
        Me.Label1.Name = "Label1"
        Me.Label1.Size = New System.Drawing.Size(149, 37)
        Me.Label1.TabIndex = 76
        Me.Label1.Text = "QUANTITY:"
        '
        'lblCargo
        '
        Me.lblCargo.AutoSize = True
        Me.lblCargo.Font = New System.Drawing.Font("Arial Narrow", 20.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblCargo.ForeColor = System.Drawing.Color.Red
        Me.lblCargo.Location = New System.Drawing.Point(314, 46)
        Me.lblCargo.Name = "lblCargo"
        Me.lblCargo.Size = New System.Drawing.Size(0, 31)
        Me.lblCargo.TabIndex = 75
        '
        'txtQty
        '
        Me.txtQty.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtQty.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtQty.Font = New System.Drawing.Font("Segoe UI", 20.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtQty.ForeColor = System.Drawing.Color.Purple
        Me.txtQty.Location = New System.Drawing.Point(201, 16)
        Me.txtQty.MaxLength = 15
        Me.txtQty.Name = "txtQty"
        Me.txtQty.Size = New System.Drawing.Size(113, 36)
        Me.txtQty.TabIndex = 1
        '
        'Label17
        '
        Me.Label17.AutoSize = True
        Me.Label17.Font = New System.Drawing.Font("Segoe UI", 36.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label17.Location = New System.Drawing.Point(2, 263)
        Me.Label17.Name = "Label17"
        Me.Label17.Size = New System.Drawing.Size(188, 65)
        Me.Label17.TabIndex = 72
        Me.Label17.Text = "GROSS:"
        '
        'Label16
        '
        Me.Label16.AutoSize = True
        Me.Label16.Font = New System.Drawing.Font("Segoe UI", 36.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label16.Location = New System.Drawing.Point(2, 208)
        Me.Label16.Name = "Label16"
        Me.Label16.Size = New System.Drawing.Size(147, 65)
        Me.Label16.TabIndex = 71
        Me.Label16.Text = "RATE:"
        '
        'Label15
        '
        Me.Label15.AutoSize = True
        Me.Label15.Font = New System.Drawing.Font("Segoe UI", 20.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label15.Location = New System.Drawing.Point(6, 139)
        Me.Label15.Name = "Label15"
        Me.Label15.Size = New System.Drawing.Size(83, 37)
        Me.Label15.TabIndex = 70
        Me.Label15.Text = "UNIT:"
        '
        'Label14
        '
        Me.Label14.AutoSize = True
        Me.Label14.Font = New System.Drawing.Font("Segoe UI", 20.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label14.Location = New System.Drawing.Point(6, 77)
        Me.Label14.Name = "Label14"
        Me.Label14.Size = New System.Drawing.Size(199, 37)
        Me.Label14.TabIndex = 69
        Me.Label14.Text = "SERVICE CODE:"
        '
        'Label13
        '
        Me.Label13.AutoSize = True
        Me.Label13.Font = New System.Drawing.Font("Segoe UI", 20.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label13.Location = New System.Drawing.Point(6, 46)
        Me.Label13.Name = "Label13"
        Me.Label13.Size = New System.Drawing.Size(112, 37)
        Me.Label13.TabIndex = 68
        Me.Label13.Text = "CARGO:"
        '
        'Label3
        '
        Me.Label3.AutoSize = True
        Me.Label3.Font = New System.Drawing.Font("Segoe UI Semibold", 12.0!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label3.Location = New System.Drawing.Point(272, 9)
        Me.Label3.Name = "Label3"
        Me.Label3.Size = New System.Drawing.Size(96, 21)
        Me.Label3.TabIndex = 69
        Me.Label3.Text = "ITEM FORM"
        '
        'frmBilladd
        '
        Me.AutoScaleDimensions = New System.Drawing.SizeF(6.0!, 13.0!)
        Me.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font
        Me.BackColor = System.Drawing.Color.White
        Me.ClientSize = New System.Drawing.Size(641, 384)
        Me.ControlBox = False
        Me.Controls.Add(Me.Label3)
        Me.Controls.Add(Me.GroupBox1)
        Me.Controls.Add(Me.Label12)
        Me.FormBorderStyle = System.Windows.Forms.FormBorderStyle.None
        Me.Icon = CType(resources.GetObject("$this.Icon"), System.Drawing.Icon)
        Me.MaximizeBox = False
        Me.MinimizeBox = False
        Me.Name = "frmBilladd"
        Me.ShowIcon = False
        Me.ShowInTaskbar = False
        Me.StartPosition = System.Windows.Forms.FormStartPosition.CenterParent
        Me.Text = "BILLING ITEM FORM"
        Me.GroupBox1.ResumeLayout(False)
        Me.GroupBox1.PerformLayout()
        Me.ResumeLayout(False)
        Me.PerformLayout()

    End Sub
    Friend WithEvents Label12 As System.Windows.Forms.Label
    Friend WithEvents GroupBox1 As System.Windows.Forms.GroupBox
    Friend WithEvents lblUnit As System.Windows.Forms.Label
    Friend WithEvents txtGross As System.Windows.Forms.TextBox
    Friend WithEvents txtRate As System.Windows.Forms.TextBox
    Friend WithEvents Label1 As System.Windows.Forms.Label
    Friend WithEvents lblCargo As System.Windows.Forms.Label
    Friend WithEvents Label17 As System.Windows.Forms.Label
    Friend WithEvents Label16 As System.Windows.Forms.Label
    Friend WithEvents Label15 As System.Windows.Forms.Label
    Friend WithEvents Label14 As System.Windows.Forms.Label
    Friend WithEvents Label13 As System.Windows.Forms.Label
    Friend WithEvents txtService As System.Windows.Forms.TextBox
    Friend WithEvents txtQty As System.Windows.Forms.TextBox
    Friend WithEvents txtCargo As System.Windows.Forms.TextBox
    Friend WithEvents txtType As System.Windows.Forms.TextBox
    Friend WithEvents Label2 As System.Windows.Forms.Label
    Friend WithEvents cbServiceName As System.Windows.Forms.ComboBox
    Friend WithEvents scipsi As System.Windows.Forms.TextBox
    Friend WithEvents tax As System.Windows.Forms.TextBox
    Friend WithEvents net As System.Windows.Forms.TextBox
    Friend WithEvents ppa As System.Windows.Forms.TextBox
    Friend WithEvents charge As System.Windows.Forms.TextBox
    Friend WithEvents txtGrosstemp As System.Windows.Forms.TextBox
    Friend WithEvents Label4 As System.Windows.Forms.Label
    Friend WithEvents txtDanger As System.Windows.Forms.TextBox
    Friend WithEvents txtRateTemp As System.Windows.Forms.TextBox
    Friend WithEvents txtDisc As System.Windows.Forms.TextBox
    Friend WithEvents Label3 As System.Windows.Forms.Label
End Class
